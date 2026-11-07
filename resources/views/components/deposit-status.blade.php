@props(['booking'])

{{-- One line on the deposit of an online booking; nothing for bookings made at the desk. --}}
@if ($booking->deposit_status)
    <flux:text size="sm" class="mt-1">
        {{ __('Deposit :amount', ['amount' => $booking::formatCents($booking->deposit_cents)]) }}:
        {{ $booking->deposit_status->label() }}
        @if ($booking->deposit_status === \App\Enums\DepositStatus::Unpaid && $booking->status === \App\Enums\BookingStatus::Pending && $booking->payment_due_at)
            · {{ __('pay by :time', ['time' => $booking->payment_due_at->setTimezone(config('app.business_timezone'))->format('H:i')]) }}
        @endif
    </flux:text>
@endif
