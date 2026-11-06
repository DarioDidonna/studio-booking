@props([
    'slots',
    'selected' => '',
    'target' => 'date',
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
            <flux:text class="col-span-full">{{ __('No free times on this day. Try another day.') }}</flux:text>
        @endforelse
    </div>

    <flux:error name="slot" />
</flux:field>
