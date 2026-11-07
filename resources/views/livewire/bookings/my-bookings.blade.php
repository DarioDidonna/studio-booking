<section class="mx-auto w-full max-w-3xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('My bookings') }}</flux:heading>
        <flux:button variant="primary" icon="plus" :href="route('bookings.create')" wire:navigate>{{ __('New booking') }}</flux:button>
    </div>

    @if ($payment === 'success')
        <flux:callout variant="success" icon="check-circle" class="mt-6" :heading="__('Payment received')" :text="__('Your booking is confirmed as soon as Stripe notifies us, usually within a few seconds. You will get an email.')" />
    @elseif ($payment === 'cancelled')
        <flux:callout variant="warning" icon="exclamation-triangle" class="mt-6" :heading="__('Payment not completed')" :text="__('The time stays reserved for :minutes minutes: you can still pay the deposit below.', ['minutes' => \App\Models\Booking::PAYMENT_WINDOW_MINUTES])" />
    @endif

    <flux:heading size="lg" level="2" class="mt-8">{{ __('Upcoming') }}</flux:heading>

    <div class="mt-4 space-y-3">
        @forelse ($this->upcoming as $booking)
            <flux:card wire:key="upcoming-{{ $booking->id }}" class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <flux:heading>{{ $booking->service->name }} · {{ $booking->operator->name }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $booking->localStartsAt()->format('d/m/Y H:i') }} · {{ $booking->formattedPrice() }}
                    </flux:text>
                    <x-deposit-status :booking="$booking" />
                </div>

                <div class="flex items-center gap-3">
                    <x-booking-status-badge :status="$booking->status" />

                    @if ($booking->status === \App\Enums\BookingStatus::Pending && $booking->stripe_checkout_url && $booking->payment_due_at?->isFuture())
                        <flux:button size="sm" variant="primary" :href="$booking->stripe_checkout_url">{{ __('Pay the deposit') }}</flux:button>
                    @endif

                    @if (now()->lessThan($booking->freeCancellationEndsAt()) && auth()->user()->can('cancel', $booking))
                        @can('reschedule', $booking)
                            <flux:button size="sm" variant="ghost" :href="route('bookings.reschedule', $booking)" wire:navigate>{{ __('Move') }}</flux:button>
                        @endcan

                        <flux:modal.trigger name="cancel-booking-{{ $booking->id }}">
                            <flux:button size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.trigger>

                        <flux:modal name="cancel-booking-{{ $booking->id }}" class="md:w-96">
                            <flux:heading size="lg">{{ __('Cancel this booking?') }}</flux:heading>
                            <flux:text class="mt-2">
                                {{ $booking->service->name }}, {{ $booking->localStartsAt()->format('d/m/Y H:i') }}
                            </flux:text>

                            <div class="mt-6 flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('Keep it') }}</flux:button>
                                </flux:modal.close>
                                <flux:button variant="danger" wire:click="cancel({{ $booking->id }})">{{ __('Cancel booking') }}</flux:button>
                            </div>
                        </flux:modal>
                    @elseif ($booking->status->occupiesSlot())
                        <flux:tooltip :content="__('Free cancellation ends :hours hours before the appointment.', ['hours' => \App\Models\Booking::FREE_CANCELLATION_HOURS])">
                            <flux:icon.information-circle variant="mini" class="text-zinc-400" />
                        </flux:tooltip>
                    @endif
                </div>
            </flux:card>
        @empty
            <flux:text>{{ __('You have no upcoming bookings.') }}</flux:text>
        @endforelse
    </div>

    @if ($this->past->isNotEmpty())
        <flux:heading size="lg" level="2" class="mt-10">{{ __('Past') }}</flux:heading>

        <div class="mt-4 space-y-3">
            @foreach ($this->past as $booking)
                <flux:card wire:key="past-{{ $booking->id }}" class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <flux:heading>{{ $booking->service->name }} · {{ $booking->operator->name }}</flux:heading>
                        <flux:text class="mt-1">{{ $booking->localStartsAt()->format('d/m/Y H:i') }}</flux:text>
                        <x-deposit-status :booking="$booking" />
                    </div>

                    <x-booking-status-badge :status="$booking->status" />
                </flux:card>
            @endforeach
        </div>
    @endif
</section>
