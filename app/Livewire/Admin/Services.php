<?php

namespace App\Livewire\Admin;

use App\Livewire\Forms\ServiceForm;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Services are deactivated rather than deleted, so past bookings keep pointing at them.
 *
 * @property-read Collection<int, Service> $services
 */
#[Title('Services')]
class Services extends Component
{
    public ServiceForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Service::class);
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->withCount('operators')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', Service::class);

        $this->form->reset();
        $this->resetValidation();
        $this->modal('service-form')->show();
    }

    public function edit(int $serviceId): void
    {
        $service = Service::query()->findOrFail($serviceId);

        $this->authorize('update', $service);

        $this->resetValidation();
        $this->form->edit($service);
        $this->modal('service-form')->show();
    }

    public function save(): void
    {
        if ($this->form->service !== null) {
            $this->authorize('update', $this->form->service);
        } else {
            $this->authorize('create', Service::class);
        }

        $this->form->save();

        unset($this->services);
        $this->modal('service-form')->close();
        Flux::toast(variant: 'success', text: __('Service saved.'));
    }

    public function toggleActive(int $serviceId): void
    {
        $service = Service::query()->findOrFail($serviceId);

        $this->authorize('update', $service);

        $service->update(['is_active' => ! $service->is_active]);

        unset($this->services);
    }
}
