<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Agenda') }}</flux:heading>
            <flux:text class="mt-1">{{ $this->operator->name }} · {{ $this->day->translatedFormat('l j F Y') }}</flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($this->operators->isNotEmpty())
                <flux:select wire:model.live="operatorId" size="sm" class="w-48">
                    @foreach ($this->operators as $operator)
                        <flux:select.option wire:key="operator-{{ $operator->id }}" :value="$operator->id">{{ $operator->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:button.group>
                <flux:button size="sm" icon="chevron-left" wire:click="previousDay" :aria-label="__('Previous day')" />
                <flux:button size="sm" wire:click="goToToday">{{ __('Today') }}</flux:button>
                <flux:button size="sm" icon="chevron-right" wire:click="nextDay" :aria-label="__('Next day')" />
            </flux:button.group>

            <flux:input wire:model.live="date" type="date" size="sm" class="w-40" />
        </div>
    </div>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>{{ __('Time') }}</flux:table.column>
            <flux:table.column>{{ __('Customer') }}</flux:table.column>
            <flux:table.column>{{ __('Service') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->bookings as $booking)
                <flux:table.row :key="$booking->id" @class(['opacity-50' => ! $booking->status->occupiesSlot() && $booking->status !== \App\Enums\BookingStatus::Completed])>
                    <flux:table.cell variant="strong" class="whitespace-nowrap">
                        {{ $booking->localStartsAt()->format('H:i') }}–{{ $booking->ends_at->setTimezone(config('app.business_timezone'))->format('H:i') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div>{{ $booking->customer->name ?? __('Deleted customer') }}</div>
                        @if ($booking->notes)
                            <flux:text size="sm" class="max-w-xs truncate">{{ $booking->notes }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $booking->service->name }}</flux:table.cell>
                    <flux:table.cell class="py-0"><x-booking-status-badge :status="$booking->status" /></flux:table.cell>
                    <flux:table.cell align="end" class="py-0">
                        @if ($booking->status === \App\Enums\BookingStatus::Confirmed && $booking->starts_at->isPast())
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="check" wire:click="markCompleted({{ $booking->id }})">{{ __('Completed') }}</flux:button>
                                <flux:button size="sm" variant="ghost" icon="user-minus" wire:click="markNoShow({{ $booking->id }})">{{ __('No-show') }}</flux:button>
                            </div>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No bookings on this day.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
