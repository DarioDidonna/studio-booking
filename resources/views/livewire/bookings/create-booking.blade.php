<section class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl" level="1">{{ __('Book an appointment') }}</flux:heading>
    <flux:text class="mt-2">{{ __('Choose a service, who you would like to see and when.') }}</flux:text>

    @if ($this->isBlockedForNoShows)
        <flux:callout variant="danger" icon="no-symbol" class="mt-8">
            <flux:callout.heading>{{ __('Online booking paused') }}</flux:callout.heading>
            <flux:callout.text>{{ __('You missed several appointments, so online booking is paused. Please contact the barbershop to book.') }}</flux:callout.text>
        </flux:callout>
    @else
        <form wire:submit="book" class="mt-8 space-y-8">
            @if ($this->customers->isNotEmpty())
                <flux:select wire:model="customerId" :label="__('Customer')" :placeholder="__('Who is the booking for?')">
                    @foreach ($this->customers as $customer)
                        <flux:select.option wire:key="customer-{{ $customer->id }}" :value="$customer->id">{{ $customer->name }} · {{ $customer->email }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="customerId" />
            @endif

            <flux:radio.group wire:model.live="serviceId" :label="__('Service')" variant="cards" class="flex-col sm:grid sm:grid-cols-2">
                @foreach ($this->services as $service)
                    <flux:radio
                        wire:key="service-{{ $service->id }}"
                        :value="$service->id"
                        :label="$service->name"
                        :description="__(':minutes min · :price', ['minutes' => $service->duration_minutes, 'price' => $service->formattedPrice()])"
                    />
                @endforeach
            </flux:radio.group>

            @if ($this->selectedService)
                {{-- Keyed by service, so the choice of the previous service never lingers on screen. --}}
                <flux:radio.group wire:key="operators-{{ $serviceId }}" wire:model.live="operatorId" :label="__('With')" variant="cards" class="flex-col sm:grid sm:grid-cols-3">
                    @if ($this->operators->count() > 1)
                        <flux:radio
                            wire:key="operator-any"
                            :value="\App\Livewire\Bookings\CreateBooking::ANY_OPERATOR"
                            :label="__('First available')"
                            :description="__('Whoever is free at that time')"
                        />
                    @endif
                    @foreach ($this->operators as $operator)
                        <flux:radio wire:key="operator-{{ $operator->id }}" :value="$operator->id" :label="$operator->name" />
                    @endforeach
                </flux:radio.group>

                <flux:input
                    wire:model.live="date"
                    type="date"
                    :label="__('Day')"
                    class="max-w-xs"
                    :min="now()->setTimezone(config('app.business_timezone'))->toDateString()"
                    :max="now()->setTimezone(config('app.business_timezone'))->addDays(\App\Models\Booking::BOOKING_HORIZON_DAYS)->toDateString()"
                />
            @endif

            @if ($operatorId !== null && $date)
                {{-- $this->slot, not $slot: in a Livewire view Blade's $slot is a component's content. --}}
                <x-free-times :slots="$this->availableSlots" :selected="$this->slot" :next-free-date="$this->nextFreeDate" target="operatorId,date" />
            @endif

            <flux:error name="operatorId" />

            @if ($this->selectedSlot)
                @if ($this->assignedOperator)
                    <flux:callout icon="user" color="amber">
                        <flux:callout.heading>{{ __('With :operator', ['operator' => $this->assignedOperator->name]) }}</flux:callout.heading>
                        <flux:callout.text>{{ __('The first barber free at :time.', ['time' => $this->selectedSlot->setTimezone(config('app.business_timezone'))->format('H:i')]) }}</flux:callout.text>
                    </flux:callout>
                @endif

                <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="2" />

                <div class="flex flex-wrap items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ $this->takesDeposit ? __('Confirm and pay the deposit') : __('Confirm booking') }}</flux:button>
                    <flux:text>
                        {{ $this->selectedService?->name }} · {{ $this->selectedSlot?->setTimezone(config('app.business_timezone'))->format('d/m/Y H:i') }}
                    </flux:text>
                </div>

                @if ($this->takesDeposit)
                    <flux:text size="sm">
                        {{ __('You pay a :amount deposit by card on Stripe. It is taken off the bill, refunded if you cancel up to :hours hours before, and kept if you do not show up.', [
                            'amount' => \Illuminate\Support\Number::currency(\App\Models\Booking::DEPOSIT_CENTS / 100, in: 'EUR', locale: 'it'),
                            'hours' => \App\Models\Booking::FREE_CANCELLATION_HOURS,
                        ]) }}
                    </flux:text>
                @endif
            @endif
        </form>
    @endif
</section>
