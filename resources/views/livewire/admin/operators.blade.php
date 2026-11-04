<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Operators') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Who works in the studio, what they do and when.') }}</flux:text>
        </div>

        <flux:modal.trigger name="operator-form">
            <flux:button variant="primary" icon="plus">{{ __('New operator') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Services') }}</flux:table.column>
            <flux:table.column>{{ __('Account') }}</flux:table.column>
            <flux:table.column>{{ __('Active') }}</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->operators as $operator)
                <flux:table.row :key="$operator->id">
                    <flux:table.cell variant="strong">{{ $operator->name }}</flux:table.cell>
                    <flux:table.cell>{{ $operator->services->pluck('name')->join(', ') ?: '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $operator->user?->email ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:switch :checked="$operator->is_active" wire:click="toggleActive({{ $operator->id }})" :aria-label="__('Active')" />
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('admin.operators.edit', $operator)" wire:navigate>
                            {{ __('Manage') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No operators yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="operator-form" class="md:w-[28rem]">
        <form wire:submit="create" class="space-y-6">
            <flux:heading size="lg">{{ __('New operator') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input
                wire:model="email"
                type="email"
                :label="__('Email (optional)')"
                :description="__('We create a staff account and email a link to set the password.')"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create and continue') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
