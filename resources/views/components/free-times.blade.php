@props([
    'slots',
    'selected' => '',
    'target' => 'date',
    'nextFreeDate' => null,
])

{{--
    The free start times as buttons that set the Livewire "slot" property.
    Values are UTC, labels are on the business clock.
--}}
<flux:field>
    <flux:label>{{ __('Time') }}</flux:label>

    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6" wire:loading.class="opacity-50" wire:target="{{ $target }}">
        @forelse ($slots as $availableSlot)
            @php($value = $availableSlot->toDateTimeString())
            @php($isSelected = $selected === $value)
            <flux:button
                wire:key="slot-{{ $value }}"
                wire:click="$set('slot', '{{ $value }}')"
                :variant="$isSelected ? 'primary' : 'outline'"
                :icon="$isSelected ? 'check' : null"
                :aria-pressed="$isSelected ? 'true' : 'false'"
                size="sm"
                type="button"
            >
                {{ $availableSlot->setTimezone(config('app.business_timezone'))->format('H:i') }}
            </flux:button>
        @empty
            <div class="col-span-full flex flex-wrap items-center gap-3">
                <flux:text>{{ __('No free times on this day.') }}</flux:text>
                @if ($nextFreeDate)
                    <flux:button size="sm" icon-trailing="arrow-right" wire:click="$set('date', '{{ $nextFreeDate }}')" type="button">
                        {{ __('First free day: :day', ['day' => \Carbon\CarbonImmutable::parse($nextFreeDate)->translatedFormat('l j F')]) }}
                    </flux:button>
                @else
                    <flux:text>{{ __('Try another day.') }}</flux:text>
                @endif
            </div>
        @endforelse
    </div>

    <flux:error name="slot" />
</flux:field>
