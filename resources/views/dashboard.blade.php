<x-layouts::app :title="__('Dashboard')">
    <section class="w-full">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
                <flux:text class="mt-1">{{ $today->translatedFormat('l j F Y') }}</flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" variant="primary" icon="plus" :href="route('bookings.create')" wire:navigate>{{ __('New booking') }}</flux:button>
                <flux:button size="sm" icon="tag" :href="route('admin.services')" wire:navigate>{{ __('Services') }}</flux:button>
                <flux:button size="sm" icon="users" :href="route('admin.operators')" wire:navigate>{{ __('Operators') }}</flux:button>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['label' => __('Bookings today'), 'value' => $todayCount],
                ['label' => __('Next 7 days'), 'value' => $upcomingCount],
                ['label' => __('Expected revenue this month'), 'value' => \App\Models\Booking::formatCents($monthRevenueCents)],
                ['label' => __('No-show rate, last 30 days'), 'value' => $noShowRate === null ? '—' : $noShowRate.'%'],
            ] as $stat)
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:text>{{ $stat['label'] }}</flux:text>
                    <flux:heading size="xl" class="mt-2 tabular-nums">{{ $stat['value'] }}</flux:heading>
                </div>
            @endforeach
        </div>

        <flux:heading size="lg" level="2" class="mt-10">{{ __("Today's appointments") }}</flux:heading>

        <flux:table class="mt-2">
            <flux:table.columns>
                <flux:table.column>{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Operator') }}</flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>{{ __('Service') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($todaysBookings as $booking)
                    <flux:table.row :key="$booking->id" @class(['opacity-50' => $booking->status === \App\Enums\BookingStatus::Cancelled])>
                        <flux:table.cell variant="strong" class="whitespace-nowrap">{{ $booking->localStartsAt()->format('H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('agenda', ['operator' => $booking->operator_id, 'date' => $today->toDateString()])" wire:navigate>{{ $booking->operator->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $booking->customer->name ?? __('Deleted customer') }}</flux:table.cell>
                        <flux:table.cell>{{ $booking->service->name }}</flux:table.cell>
                        <flux:table.cell class="py-0"><x-booking-status-badge :status="$booking->status" /></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">{{ __('No bookings on this day.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>
</x-layouts::app>
