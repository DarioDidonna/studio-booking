<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-ink text-cream antialiased">
        <div class="barber-stripes h-1.5" aria-hidden="true"></div>

        <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-5">
            <x-app-logo href="{{ route('home') }}" />

            <nav class="flex items-center gap-1">
                @auth
                    <flux:button size="sm" variant="ghost" :href="route('dashboard')">{{ __('My account') }}</flux:button>
                @else
                    <flux:button size="sm" variant="ghost" :href="route('login')">{{ __('Log in') }}</flux:button>
                @endauth
                <flux:button size="sm" variant="primary" :href="route('bookings.create')">{{ __('Book') }}</flux:button>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl px-6">
            <section class="grid gap-12 border-t border-zinc-800 py-12 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:gap-x-20 lg:py-20">
                <div>
                    <h1 class="font-display text-5xl leading-[1.05] font-black sm:text-6xl">{{ config('app.name') }}</h1>

                    <p class="mt-6 max-w-md text-lg text-zinc-300">
                        {{ __('Haircuts, beards and hot-towel shaves. Pick your barber and a free time, and we confirm by email.') }}
                    </p>

                    <div class="mt-8">
                        <flux:button variant="primary" icon-trailing="arrow-right" :href="route('bookings.create')">{{ __('Book an appointment') }}</flux:button>
                    </div>

                    <p class="mt-4 text-sm text-zinc-500">
                        {{ __('Reminder the day before · free cancellation up to :hours hours before', ['hours' => \App\Models\Booking::FREE_CANCELLATION_HOURS]) }}
                    </p>
                </div>

                <div id="services" class="scroll-mt-8 self-start lg:col-start-2 lg:row-span-2 lg:row-start-1 rounded-md border border-zinc-800 bg-zinc-900 p-6 sm:p-8">
                    <h2 class="font-display text-3xl font-bold">{{ __('Price list') }}</h2>

                    <ul class="mt-4 divide-y divide-zinc-800">
                        @forelse ($services as $service)
                            <li class="py-4">
                                <div class="flex items-baseline gap-3">
                                    <h3 class="font-display text-lg font-bold">{{ $service->name }}</h3>
                                    <span class="flex-1 border-b border-dotted border-zinc-600" aria-hidden="true"></span>
                                    <span class="font-display text-lg font-bold text-brass tabular-nums">{{ $service->formattedPrice() }}</span>
                                </div>
                                <p class="mt-1 text-sm text-zinc-400">
                                    {{ __(':minutes min', ['minutes' => $service->duration_minutes]) }}@if ($service->description) · {{ $service->description }}@endif
                                </p>
                            </li>
                        @empty
                            <li class="py-4 text-zinc-400">{{ __('Our services will be published soon.') }}</li>
                        @endforelse
                    </ul>
                </div>

                <div class="lg:col-start-1 lg:row-start-2">
                    <h2 class="font-label text-xs text-brass">{{ __('Opening hours') }}</h2>

                    <dl class="mt-4 max-w-sm divide-y divide-zinc-800 text-sm">
                        @foreach ($openingHours as $day)
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-zinc-300">{{ $day['day'] }}</dt>
                                <dd @class(['tabular-nums', 'text-zinc-500' => $day['intervals'] === []])>
                                    {{ $day['intervals'] === [] ? __('Closed') : implode(', ', $day['intervals']) }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>

            @if ($barbers->isNotEmpty())
                <section class="grid gap-6 border-t border-zinc-800 py-12 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:gap-20 lg:py-16">
                    <h2 class="font-display text-3xl font-bold">{{ __('Our barbers') }}</h2>

                    <ul class="divide-y divide-zinc-800">
                        @foreach ($barbers as $barber)
                            <li class="py-4 first:pt-0">
                                <h3 class="font-display text-xl font-bold">{{ $barber->name }}</h3>
                                <p class="mt-1 text-sm text-zinc-400">{{ $barber->services->pluck('name')->implode(', ') }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </main>

        <footer class="mx-auto w-full max-w-6xl px-6">
            <div class="flex flex-wrap justify-between gap-2 border-t border-zinc-800 py-8 text-sm text-zinc-500">
                <span>© {{ now()->year }} {{ config('app.name') }}</span>
                <a href="{{ route('bookings.create') }}" class="text-zinc-300 underline-offset-4 hover:text-brass hover:underline">{{ __('Book an appointment') }}</a>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
