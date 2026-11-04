<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-900">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-6 py-6">
            <x-app-logo href="{{ route('home') }}" />

            <nav class="flex items-center gap-2">
                @auth
                    <flux:button variant="ghost" :href="route('dashboard')">{{ __('My account') }}</flux:button>
                @else
                    <flux:button variant="ghost" :href="route('login')">{{ __('Log in') }}</flux:button>

                    @if (Route::has('register'))
                        <flux:button variant="outline" :href="route('register')">{{ __('Register') }}</flux:button>
                    @endif
                @endauth
            </nav>
        </header>

        <main class="mx-auto w-full max-w-5xl px-6">
            <section class="py-16 sm:py-24">
                <flux:heading size="xl" level="1" class="text-4xl! sm:text-5xl!">
                    {{ __('Book your next appointment in a few clicks.') }}
                </flux:heading>

                <flux:text size="lg" class="mt-6 max-w-2xl">
                    {{ __('Choose a service, pick who you would like to see and grab a free time. You get a confirmation by email and a reminder the day before.') }}
                </flux:text>

                <div class="mt-10 flex flex-wrap gap-3">
                    <flux:button variant="primary" icon-trailing="arrow-right" :href="route('bookings.create')">
                        {{ __('Book now') }}
                    </flux:button>
                    <flux:button variant="ghost" href="#services">{{ __('See services and prices') }}</flux:button>
                </div>
            </section>

            <section id="services" class="scroll-mt-8 pb-16">
                <flux:heading size="lg" level="2">{{ __('Services') }}</flux:heading>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($services as $service)
                        <flux:card class="flex flex-col">
                            <flux:heading>{{ $service->name }}</flux:heading>

                            @if ($service->description)
                                <flux:text class="mt-2 flex-1">{{ $service->description }}</flux:text>
                            @endif

                            <div class="mt-4 flex items-center justify-between">
                                <flux:badge size="sm" icon="clock">{{ __(':minutes min', ['minutes' => $service->duration_minutes]) }}</flux:badge>
                                <flux:heading>{{ $service->formattedPrice() }}</flux:heading>
                            </div>
                        </flux:card>
                    @empty
                        <flux:text>{{ __('Our services will be published soon.') }}</flux:text>
                    @endforelse
                </div>
            </section>

            <section class="border-t border-zinc-200 py-16 dark:border-zinc-700">
                <flux:heading size="lg" level="2">{{ __('How it works') }}</flux:heading>

                <div class="mt-6 grid gap-6 sm:grid-cols-3">
                    <div>
                        <flux:icon.calendar-days class="text-zinc-400" />
                        <flux:heading class="mt-3">{{ __('1. Pick a time') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Only times that are really free are shown, updated live.') }}</flux:text>
                    </div>
                    <div>
                        <flux:icon.envelope class="text-zinc-400" />
                        <flux:heading class="mt-3">{{ __('2. Get it confirmed') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('You receive an email right away and a reminder 24 hours before.') }}</flux:text>
                    </div>
                    <div>
                        <flux:icon.arrow-uturn-left class="text-zinc-400" />
                        <flux:heading class="mt-3">{{ __('3. Change your mind') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Cancel for free up to :hours hours before your appointment.', ['hours' => \App\Models\Booking::FREE_CANCELLATION_HOURS]) }}</flux:text>
                    </div>
                </div>
            </section>
        </main>

        <footer class="mx-auto w-full max-w-5xl px-6 py-8">
            <flux:text size="sm">© {{ now()->year }} {{ config('app.name') }}</flux:text>
        </footer>

        @fluxScripts
    </body>
</html>
