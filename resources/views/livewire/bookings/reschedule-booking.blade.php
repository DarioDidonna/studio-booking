<section class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl" level="1">{{ __('Move booking') }}</flux:heading>
    <flux:text class="mt-2">
        {{ $booking->service->name }} · {{ $booking->operator->name }} ·
        {{ __('now on :date', ['date' => $booking->localStartsAt()->format('d/m/Y H:i')]) }}
    </flux:text>

    <form wire:submit="save" class="mt-8 space-y-8">
        <flux:input
            wire:model.live="date"
            type="date"
            :label="__('New day')"
            class="max-w-xs"
            :min="now()->setTimezone(config('app.business_timezone'))->toDateString()"
            :max="now()->setTimezone(config('app.business_timezone'))->addDays(\App\Models\Booking::BOOKING_HORIZON_DAYS)->toDateString()"
        />

        {{-- $this->slot, not $slot: in a Livewire view Blade's $slot is a component's content. --}}
        <x-free-times :slots="$this->availableSlots" :selected="$this->slot" />

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit" :disabled="$this->slot === ''">{{ __('Move booking') }}</flux:button>
            <flux:button variant="ghost" :href="auth()->user()->isAdmin() ? route('agenda') : route('bookings.index')" wire:navigate>{{ __('Back') }}</flux:button>
        </div>
    </form>
</section>
