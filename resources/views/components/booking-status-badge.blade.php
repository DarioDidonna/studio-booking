@props(['status'])

<flux:badge size="sm" :color="$status->color()">{{ $status->label() }}</flux:badge>
