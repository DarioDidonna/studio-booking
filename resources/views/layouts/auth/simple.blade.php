<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-ink antialiased">
        <div class="barber-stripes h-1.5" aria-hidden="true"></div>

        <div class="flex min-h-[calc(100svh-0.5rem)] flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-8">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3" wire:navigate>
                    <span class="flex size-14 items-center justify-center rounded-full bg-zinc-900 ring-1 ring-brass/60">
                        <x-app-logo-icon class="size-9 text-brass" />
                    </span>
                    <span class="font-display text-2xl font-bold tracking-wide text-cream">{{ config('app.name', 'Laravel') }}</span>
                </a>

                <div class="flex flex-col gap-6 rounded-xl border border-zinc-700 bg-zinc-900 p-8">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
