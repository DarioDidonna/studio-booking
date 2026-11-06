{{-- A barber pole: caps, a glass tube and diagonal stripes, all in the current color. --}}
@php($clipId = 'pole-'.\Illuminate\Support\Str::random(8))

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="currentColor" {{ $attributes }}>
    <defs>
        <clipPath id="{{ $clipId }}">
            <rect x="11" y="7" width="10" height="18" rx="1" />
        </clipPath>
    </defs>
    <circle cx="16" cy="2.5" r="2" />
    <rect x="8.5" y="4" width="15" height="3" rx="1.5" />
    <rect x="8.5" y="25" width="15" height="3" rx="1.5" />
    <rect x="12.5" y="28" width="7" height="3" rx="1" />
    <rect x="11" y="7" width="10" height="18" fill="none" stroke="currentColor" stroke-width="1.5" />
    <g clip-path="url(#{{ $clipId }})">
        <path d="M5 13 21 -3v5L5 18Z" />
        <path d="M5 21 27 -1v5L5 26Z" />
        <path d="M5 29 27 7v5L5 34Z" />
        <path d="M11 31 27 15v5L11 36Z" />
    </g>
</svg>
