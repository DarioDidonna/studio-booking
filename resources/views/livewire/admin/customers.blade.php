<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Customers') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Booking history and no-shows of every customer.') }}</flux:text>
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or email')" class="w-full sm:w-72" clearable />
    </div>

    <flux:table :paginate="$this->customers" class="mt-6">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Bookings') }}</flux:table.column>
            <flux:table.column align="end">{{ __('No-shows') }}</flux:table.column>
            <flux:table.column>{{ __('Last visit') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->customers as $customer)
                <flux:table.row :key="$customer->id">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('admin.customers.show', $customer)" wire:navigate>{{ $customer->name }}</flux:link>
                        @if ($customer->recent_no_shows_count >= \App\Models\Booking::NO_SHOW_LIMIT)
                            <flux:badge size="sm" color="red" class="ms-2">{{ __('Blocked') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $customer->email }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ $customer->bookings_count }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ $customer->no_shows_count }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $customer->last_visit_at?->setTimezone(config('app.business_timezone'))->format('d/m/Y') ?? '—' }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No customers found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
