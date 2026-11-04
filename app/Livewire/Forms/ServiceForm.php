<?php

namespace App\Livewire\Forms;

use App\Models\Service;
use Livewire\Form;

class ServiceForm extends Form
{
    public ?Service $service = null;

    public string $name = '';

    public string $description = '';

    public string $durationMinutes = '30';

    /**
     * The price in euros as typed, accepting both "25.50" and "25,50".
     */
    public string $price = '';

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'durationMinutes' => ['required', 'integer', 'min:5', 'max:480', 'multiple_of:5'],
            'price' => ['required', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'price.regex' => __('Enter a price in euros, for example 25 or 25,50.'),
        ];
    }

    public function edit(Service $service): void
    {
        $this->service = $service;
        $this->name = $service->name;
        $this->description = $service->description ?? '';
        $this->durationMinutes = (string) $service->duration_minutes;
        $this->price = number_format($service->price_cents / 100, 2, ',', '');
    }

    public function save(): Service
    {
        $this->validate();

        $service = $this->service ?? new Service;

        $service->fill([
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
            'duration_minutes' => (int) $this->durationMinutes,
            'price_cents' => $this->priceInCents(),
        ])->save();

        $this->reset();

        return $service;
    }

    /**
     * Converted with string arithmetic, so 19,99 never becomes 1998 through float rounding.
     */
    private function priceInCents(): int
    {
        [$euros, $cents] = array_pad(explode(',', str_replace('.', ',', $this->price)), 2, '0');

        return ((int) $euros * 100) + (int) str_pad(substr($cents, 0, 2), 2, '0');
    }
}
