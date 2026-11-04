<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Services') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Inactive services cannot be booked but stay on past bookings.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New service') }}</flux:button>
    </div>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Duration') }}</flux:table.column>
            <flux:table.column>{{ __('Price') }}</flux:table.column>
            <flux:table.column>{{ __('Operators') }}</flux:table.column>
            <flux:table.column>{{ __('Active') }}</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->services as $service)
                <flux:table.row :key="$service->id">
                    <flux:table.cell variant="strong">{{ $service->name }}</flux:table.cell>
                    <flux:table.cell>{{ __(':minutes min', ['minutes' => $service->duration_minutes]) }}</flux:table.cell>
                    <flux:table.cell>{{ $service->formattedPrice() }}</flux:table.cell>
                    <flux:table.cell>{{ $service->operators_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:switch :checked="$service->is_active" wire:click="toggleActive({{ $service->id }})" :aria-label="__('Active')" />
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $service->id }})">{{ __('Edit') }}</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No services yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="service-form" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->service ? __('Edit service') : __('New service') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" required />
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="2" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="form.durationMinutes" :label="__('Duration (minutes)')" type="number" min="5" step="5" required />
                <flux:input wire:model="form.price" :label="__('Price (€)')" inputmode="decimal" placeholder="25,00" required />
            </div>

            <flux:text size="sm">{{ __('Changing the price does not affect bookings that already exist.') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
