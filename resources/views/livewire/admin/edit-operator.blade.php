<section class="mx-auto w-full max-w-3xl space-y-10">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.operators')" wire:navigate>{{ __('Operators') }}</flux:button>
        <flux:heading size="xl" level="1" class="mt-2">{{ $operator->name }}</flux:heading>
        @if ($operator->user)
            <flux:text class="mt-1">{{ __('Staff account: :email', ['email' => $operator->user->email]) }}</flux:text>
        @endif
    </div>

    <form wire:submit="saveDetails" class="space-y-6">
        <flux:heading size="lg" level="2">{{ __('Details') }}</flux:heading>

        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:checkbox.group wire:model="serviceIds" :label="__('Services offered')">
            @foreach ($this->services as $service)
                <flux:checkbox
                    wire:key="service-{{ $service->id }}"
                    :value="$service->id"
                    :label="$service->name"
                    :description="$service->is_active ? null : __('Inactive')"
                />
            @endforeach
        </flux:checkbox.group>

        <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
    </form>

    <flux:separator />

    <div class="space-y-4">
        <div>
            <flux:heading size="lg" level="2">{{ __('Weekly working hours') }}</flux:heading>
            <flux:text class="mt-1">{{ __('In the studio\'s local time. Add two intervals on the same day for a lunch break.') }}</flux:text>
        </div>

        <div class="space-y-2">
            @forelse ($this->workingHours as $workingHour)
                <div wire:key="hour-{{ $workingHour->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                    <flux:text>
                        <span class="inline-block w-28 font-medium text-zinc-800 dark:text-white">{{ $workingHour->weekday->label() }}</span>
                        {{ substr($workingHour->start_time, 0, 5) }}–{{ substr($workingHour->end_time, 0, 5) }}
                    </flux:text>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeWorkingHour({{ $workingHour->id }})" :aria-label="__('Remove')" />
                </div>
            @empty
                <flux:text>{{ __('No working hours yet: this operator cannot be booked.') }}</flux:text>
            @endforelse
        </div>

        <form wire:submit="addWorkingHour" class="grid grid-cols-2 items-end gap-3 sm:grid-cols-4">
            <flux:select wire:model="hourWeekday" :label="__('Day')">
                @foreach (\App\Enums\Weekday::cases() as $weekday)
                    <flux:select.option :value="$weekday->value">{{ $weekday->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="hourStart" type="time" :label="__('From')" />
            <flux:input wire:model="hourEnd" type="time" :label="__('To')" />
            <flux:button type="submit" icon="plus">{{ __('Add') }}</flux:button>
        </form>
        <flux:error name="hourStart" />
        <flux:error name="hourEnd" />
    </div>

    <flux:separator />

    <div class="space-y-4">
        <div>
            <flux:heading size="lg" level="2">{{ __('Time off and closures') }}</flux:heading>
            <flux:text class="mt-1">{{ __('No new bookings can be made in these periods. Existing bookings are cancelled and their customers notified by email.') }}</flux:text>
        </div>

        <div class="space-y-2">
            @forelse ($this->timeOffs as $timeOff)
                <div wire:key="off-{{ $timeOff->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                    <flux:text>
                        {{ $timeOff->starts_at->setTimezone(config('app.business_timezone'))->format('d/m/Y H:i') }}
                        → {{ $timeOff->ends_at->setTimezone(config('app.business_timezone'))->format('d/m/Y H:i') }}
                        @if ($timeOff->reason)
                            · {{ $timeOff->reason }}
                        @endif
                    </flux:text>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeTimeOff({{ $timeOff->id }})" :aria-label="__('Remove')" />
                </div>
            @empty
                <flux:text>{{ __('No upcoming time off.') }}</flux:text>
            @endforelse
        </div>

        <form wire:submit="addTimeOff" class="grid items-end gap-3 sm:grid-cols-4">
            <flux:input wire:model="offStartsAt" type="datetime-local" :label="__('From')" />
            <flux:input wire:model="offEndsAt" type="datetime-local" :label="__('To')" />
            <flux:input wire:model="offReason" :label="__('Reason')" :placeholder="__('Holiday')" />
            <flux:button type="submit" icon="plus">{{ __('Add') }}</flux:button>
        </form>
    </div>
</section>
