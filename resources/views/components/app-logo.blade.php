@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Laravel')" {{ $attributes->class('font-display tracking-wide') }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-ink ring-1 ring-brass/60">
            <x-app-logo-icon class="size-6 text-brass" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Laravel')" {{ $attributes->class('font-display tracking-wide') }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-ink ring-1 ring-brass/60">
            <x-app-logo-icon class="size-6 text-brass" />
        </x-slot>
    </flux:brand>
@endif
