<section class="w-full">
    <flux:link :href="route('admin.customers')" wire:navigate class="text-sm">← {{ __('Customers') }}</flux:link>

    <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $customer->name }}</flux:heading>
            <flux:text class="mt-1">
                <flux:link href="mailto:{{ $customer->email }}">{{ $customer->email }}</flux:link>
                · {{ __('Customer since :date', ['date' => $customer->created_at?->setTimezone(config('app.business_timezone'))->format('d/m/Y') ?? '—']) }}
            </flux:text>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('bookings.create')" wire:navigate>{{ __('New booking') }}</flux:button>
    </div>

    @if ($customer->isBlockedForNoShows())
        <flux:callout variant="danger" icon="no-symbol" class="mt-6">
            <flux:callout.heading>{{ __('Online booking paused') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __(':count no-shows: this customer can only book through the barbershop.', ['count' => $customer->recentNoShowsCount()]) }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" wire:click="clearNoShows">{{ __('Enable online booking') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['label' => __('Completed visits'), 'value' => $this->totals['completed']],
            ['label' => __('Cancellations'), 'value' => $this->totals['cancelled']],
            ['label' => __('No-shows'), 'value' => $this->totals['no_shows']],
            ['label' => __('Spent'), 'value' => \Illuminate\Support\Number::currency($this->totals['spent_cents'] / 100, in: 'EUR', locale: 'it')],
        ] as $stat)
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <dt><flux:text>{{ $stat['label'] }}</flux:text></dt>
                <dd><flux:heading size="xl" class="mt-2 tabular-nums">{{ $stat['value'] }}</flux:heading></dd>
            </div>
        @endforeach
    </dl>

    <flux:heading size="lg" level="2" class="mt-10">{{ __('Booking history') }}</flux:heading>

    <flux:table class="mt-2">
        <flux:table.columns>
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column>{{ __('Service') }}</flux:table.column>
            <flux:table.column>{{ __('Operator') }}</flux:table.column>
            <flux:table.column>{{ __('Notes') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->bookings as $booking)
                <flux:table.row :key="$booking->id">
                    <flux:table.cell class="whitespace-nowrap">{{ $booking->localStartsAt()->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $booking->service->name }}</flux:table.cell>
                    <flux:table.cell>{{ $booking->operator->name }}</flux:table.cell>
                    <flux:table.cell><span class="block max-w-xs truncate">{{ $booking->notes ?? '—' }}</span></flux:table.cell>
                    <flux:table.cell class="py-0"><x-booking-status-badge :status="$booking->status" /></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No bookings yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
