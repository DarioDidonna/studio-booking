<?php

namespace Tests\Feature\Payments;

use App\Enums\BookingStatus;
use App\Enums\DepositStatus;
use App\Enums\Weekday;
use App\Jobs\RefundDeposit;
use App\Livewire\Bookings\CreateBooking;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Services\BookingService;
use App\Services\Payments\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Stripe\WebhookSignature;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

/**
 * 2027-03-15 is a Monday; 10:00 in Rome is 09:00 UTC on that day.
 */
class DepositTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test';

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.business_timezone' => 'Europe/Rome',
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
        $this->travelTo(CarbonImmutable::parse('2027-03-01 12:00', 'UTC'));
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    public function test_online_booking_waits_for_the_deposit_and_holds_the_slot(): void
    {
        Notification::fake();
        [$operator, $service] = $this->operatorOffering();
        $customer = User::factory()->create();

        $booking = app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);

        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame(DepositStatus::Unpaid, $booking->deposit_status);
        $this->assertSame(Booking::DEPOSIT_CENTS, $booking->deposit_cents);
        $this->assertSame('2027-03-01 12:30:00', $booking->payment_due_at->toDateTimeString());
        Notification::assertNothingSent();
    }

    public function test_admin_booking_at_the_desk_takes_no_deposit(): void
    {
        [$operator, $service] = $this->operatorOffering();

        $booking = app(BookingService::class)->book(User::factory()->create(), $operator, $service, $this->mondayAt('10:00'), User::factory()->admin()->create());

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->deposit_status);
    }

    public function test_deposits_are_off_without_stripe_keys(): void
    {
        config(['services.stripe.secret' => null]);
        [$operator, $service] = $this->operatorOffering();
        $customer = User::factory()->create();

        $booking = app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->deposit_status);
    }

    public function test_customer_is_sent_to_stripe_checkout_after_confirming(): void
    {
        [$operator, $service] = $this->operatorOffering();
        $customer = User::factory()->create();

        $component = Livewire::actingAs($customer)
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->set('slot', '2027-03-15 09:00:00')
            ->assertSee('Conferma e paga la caparra')
            ->call('book')
            ->assertHasNoErrors();

        $booking = Booking::query()->sole();
        $component->assertRedirect("https://checkout.stripe.test/pay/cs_test_{$booking->id}");
        $this->assertSame("cs_test_{$booking->id}", $booking->stripe_checkout_session_id);
        $this->assertSame('2027-03-01 12:30:00', $this->gateway->checkouts[0]['expires_at']->utc()->toDateTimeString());
    }

    public function test_slot_is_released_at_once_when_stripe_cannot_be_reached(): void
    {
        $this->gateway->failCheckout = true;
        [$operator, $service] = $this->operatorOffering();
        $customer = User::factory()->create();

        Livewire::actingAs($customer)
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->set('slot', '2027-03-15 09:00:00')
            ->call('book')
            ->assertHasErrors('slot')
            ->assertNoRedirect();

        $this->assertSame(BookingStatus::Cancelled, Booking::query()->sole()->status);
    }

    public function test_webhook_with_a_bad_signature_is_rejected(): void
    {
        $booking = $this->pendingBooking();
        $payload = $this->event('evt_1', 'checkout.session.completed', $booking);

        $this->postJsonWebhook($payload, WebhookSignature::generateSignatureHeader($payload, 'whsec_wrong'))
            ->assertStatus(400);

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_paid_checkout_confirms_the_booking_once_even_if_stripe_repeats_the_event(): void
    {
        Notification::fake();
        $booking = $this->pendingBooking();
        $payload = $this->event('evt_1', 'checkout.session.completed', $booking);

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(DepositStatus::Paid, $booking->deposit_status);
        $this->assertSame('pi_test_1', $booking->stripe_payment_intent_id);
        Notification::assertSentToTimes($booking->customer, BookingConfirmed::class, 1);
        $this->assertDatabaseCount('stripe_webhook_events', 1);
    }

    public function test_expired_checkout_releases_the_slot(): void
    {
        Notification::fake();
        $booking = $this->pendingBooking();

        $this->postSignedWebhook($this->event('evt_2', 'checkout.session.expired', $booking))->assertOk();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        Notification::assertSentTo($booking->customer, BookingCancelled::class);
    }

    public function test_payment_that_arrives_after_the_slot_was_released_is_refunded(): void
    {
        $booking = $this->pendingBooking();
        $this->artisan('bookings:release-unpaid')->assertSuccessful();
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);

        $this->travel(Booking::PAYMENT_WINDOW_MINUTES + 1)->minutes();
        $this->artisan('bookings:release-unpaid')->assertSuccessful();
        $this->postSignedWebhook($this->event('evt_3', 'checkout.session.completed', $booking))->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame(DepositStatus::Refunded, $booking->deposit_status);
        $this->assertSame(['refund-deposit-'.$booking->id => 'pi_test_1'], $this->gateway->refunds);
    }

    public function test_deposit_is_refunded_when_the_customer_cancels_in_time(): void
    {
        $booking = $this->paidBooking();

        app(BookingService::class)->cancel($booking, $booking->customer);

        $this->assertSame(DepositStatus::Refunded, $booking->fresh()->deposit_status);
        $this->assertCount(1, $this->gateway->refunds);
    }

    public function test_refund_job_run_twice_refunds_once(): void
    {
        $booking = $this->paidBooking();

        (new RefundDeposit($booking))->handle($this->gateway);
        (new RefundDeposit($booking))->handle($this->gateway);

        $this->assertCount(1, $this->gateway->refunds);
        $this->assertSame(DepositStatus::Refunded, $booking->fresh()->deposit_status);
    }

    public function test_deposit_is_kept_after_a_no_show_and_taken_off_the_bill_after_a_visit(): void
    {
        $noShow = $this->paidBooking();
        $visit = $this->paidBooking('2027-03-15 13:00');
        $this->travelTo(CarbonImmutable::parse('2027-03-15 16:00', 'UTC'));
        $admin = User::factory()->admin()->create();

        app(BookingService::class)->markNoShow($noShow, $admin);
        app(BookingService::class)->markCompleted($visit, $admin);

        $this->assertSame(DepositStatus::Kept, $noShow->fresh()->deposit_status);
        $this->assertSame(DepositStatus::Applied, $visit->fresh()->deposit_status);
        $this->assertSame([], $this->gateway->refunds);
    }

    /**
     * @return array{Operator, Service}
     */
    private function operatorOffering(): array
    {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $operator = Operator::factory()->hasAttached($service)->create();
        $operator->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);

        return [$operator, $service];
    }

    private function mondayAt(string $localTime): CarbonImmutable
    {
        return CarbonImmutable::parse("2027-03-15 {$localTime}", 'Europe/Rome');
    }

    private function pendingBooking(string $utcStart = '2027-03-15 09:00'): Booking
    {
        $startsAt = CarbonImmutable::parse($utcStart, 'UTC');

        return Booking::factory()->create([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'status' => BookingStatus::Pending,
            'deposit_cents' => Booking::DEPOSIT_CENTS,
            'deposit_status' => DepositStatus::Unpaid,
            'payment_due_at' => now()->addMinutes(Booking::PAYMENT_WINDOW_MINUTES),
            'stripe_checkout_session_id' => 'cs_test_'.fake()->unique()->numberBetween(1, 99999),
        ]);
    }

    private function paidBooking(string $utcStart = '2027-03-15 09:00'): Booking
    {
        $booking = $this->pendingBooking($utcStart);
        $booking->update([
            'status' => BookingStatus::Confirmed,
            'deposit_status' => DepositStatus::Paid,
            'stripe_payment_intent_id' => 'pi_test_'.$booking->id,
            'payment_due_at' => null,
        ]);

        return $booking;
    }

    /**
     * A Stripe event about the booking's Checkout Session, as Stripe sends it.
     */
    private function event(string $id, string $type, Booking $booking): string
    {
        return (string) json_encode([
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => [
                'id' => $booking->stripe_checkout_session_id,
                'object' => 'checkout.session',
                'payment_status' => $type === 'checkout.session.completed' ? 'paid' : 'unpaid',
                'payment_intent' => $type === 'checkout.session.completed' ? 'pi_test_1' : null,
                'metadata' => ['booking_id' => (string) $booking->id],
            ]],
        ]);
    }

    private function postSignedWebhook(string $payload): TestResponse
    {
        return $this->postJsonWebhook($payload, WebhookSignature::generateSignatureHeader($payload, self::WEBHOOK_SECRET));
    }

    private function postJsonWebhook(string $payload, string $signature): TestResponse
    {
        return $this->call('POST', route('stripe.webhook'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], content: $payload);
    }
}
