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
                <div class="grid gap-6 sm:grid-cols-2">
                    {{-- Keyed by service: a reused <select> would keep showing the operator of the previous service. --}}
                    <flux:select wire:key="operator-select-{{ $serviceId }}" wire:model.live="operatorId" :label="__('With')" :placeholder="__('Choose who…')">
                        @if ($this->operators->count() > 1)
                            <flux:select.option wire:key="operator-any" :value="\App\Livewire\Bookings\CreateBooking::ANY_OPERATOR">{{ __('First available barber') }}</flux:select.option>
                        @endif
                        @foreach ($this->operators as $operator)
                            <flux:select.option wire:key="operator-{{ $operator->id }}" :value="$operator->id">{{ $operator->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input
                        wire:model.live="date"
                        type="date"
                        :label="__('Day')"
                        :min="now()->setTimezone(config('app.business_timezone'))->toDateString()"
                        :max="now()->setTimezone(config('app.business_timezone'))->addDays(\App\Models\Booking::BOOKING_HORIZON_DAYS)->toDateString()"
                    />
                </div>
            @endif

            @if ($operatorId !== null && $date)
                {{-- $this->slot, not $slot: in a Livewire view Blade's $slot is a component's content. --}}
                <x-free-times :slots="$this->availableSlots" :selected="$this->slot" target="operatorId,date" />
            @endif

            <flux:error name="operatorId" />

            @if ($this->selectedSlot)
                <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="2" />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Confirm booking') }}</flux:button>
                    <flux:text>
                        {{ $this->selectedService?->name }} · {{ $this->selectedSlot?->setTimezone(config('app.business_timezone'))->format('d/m/Y H:i') }}
                    </flux:text>
                </div>
            @endif
        </form>
    @endif
</section>
