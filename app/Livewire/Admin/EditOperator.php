<?php

namespace App\Livewire\Admin;

use App\Enums\BookingStatus;
use App\Enums\Weekday;
use App\Models\Operator;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Collection<int, Service> $services
 * @property-read Collection<int, WorkingHour> $workingHours
 * @property-read Collection<int, TimeOff> $timeOffs
 */
class EditOperator extends Component
{
    #[Locked]
    public Operator $operator;

    public string $name = '';

    /**
     * Checkbox values arrive from the browser as strings.
     *
     * @var array<int, int|string>
     */
    public array $serviceIds = [];

    public string $hourWeekday = '1';

    public string $hourStart = '09:00';

    public string $hourEnd = '13:00';

    /**
     * Local wall-clock times from a datetime-local input, formatted as Y-m-d\TH:i.
     */
    public string $offStartsAt = '';

    public string $offEndsAt = '';

    public string $offReason = '';

    public function mount(Operator $operator): void
    {
        $this->authorize('update', $operator);

        $this->operator = $operator;
        $this->name = $operator->name;
        $this->serviceIds = $operator->services->map(fn (Service $service): int => $service->id)->all();
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, WorkingHour>
     */
    #[Computed]
    public function workingHours(): Collection
    {
        return $this->operator->workingHours()->orderBy('weekday')->orderBy('start_time')->get();
    }

    /**
     * @return Collection<int, TimeOff>
     */
    #[Computed]
    public function timeOffs(): Collection
    {
        return $this->operator->timeOffs()->where('ends_at', '>', Date::now())->orderBy('starts_at')->get();
    }

    public function saveDetails(): void
    {
        $this->authorize('update', $this->operator);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'serviceIds' => ['array'],
            'serviceIds.*' => ['integer', Rule::exists('services', 'id')],
        ]);

        $this->operator->update(['name' => $validated['name']]);
        $this->operator->services()->sync($validated['serviceIds']);

        Flux::toast(variant: 'success', text: __('Operator saved.'));
    }

    public function addWorkingHour(): void
    {
        $this->authorize('update', $this->operator);

        $this->validate([
            'hourWeekday' => ['required', Rule::enum(Weekday::class)],
            'hourStart' => ['required', 'date_format:H:i'],
            'hourEnd' => ['required', 'date_format:H:i', 'after:hourStart'],
        ]);

        $start = "{$this->hourStart}:00";
        $end = "{$this->hourEnd}:00";

        $overlaps = $this->operator->workingHours()
            ->where('weekday', $this->hourWeekday)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();

        if ($overlaps) {
            $this->addError('hourStart', __('This interval overlaps another one on the same day.'));

            return;
        }

        $this->operator->workingHours()->create([
            'weekday' => Weekday::from((int) $this->hourWeekday),
            'start_time' => $start,
            'end_time' => $end,
        ]);

        unset($this->workingHours);
    }

    public function removeWorkingHour(int $workingHourId): void
    {
        $this->authorize('update', $this->operator);

        $this->operator->workingHours()->whereKey($workingHourId)->delete();

        unset($this->workingHours);
    }

    public function addTimeOff(): void
    {
        $this->authorize('update', $this->operator);

        $this->validate([
            'offStartsAt' => ['required', 'date_format:Y-m-d\TH:i'],
            'offEndsAt' => ['required', 'date_format:Y-m-d\TH:i', 'after:offStartsAt'],
            'offReason' => ['nullable', 'string', 'max:100'],
        ]);

        $timezone = config('app.business_timezone');
        $startsAt = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->offStartsAt, $timezone)->utc();
        $endsAt = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->offEndsAt, $timezone)->utc();

        $this->operator->timeOffs()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $this->offReason !== '' ? $this->offReason : null,
        ]);

        $this->reset('offStartsAt', 'offEndsAt', 'offReason');
        unset($this->timeOffs);

        $conflicts = $this->operator->bookings()
            ->whereIn('status', BookingStatus::slotOccupyingCases())
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->count();

        if ($conflicts > 0) {
            Flux::toast(
                variant: 'warning',
                text: trans_choice('{1} One booking falls in this period: it is not cancelled automatically.|[2,*] :count bookings fall in this period: they are not cancelled automatically.', $conflicts),
                duration: 0,
            );

            return;
        }

        Flux::toast(variant: 'success', text: __('Time off added.'));
    }

    public function removeTimeOff(int $timeOffId): void
    {
        $this->authorize('update', $this->operator);

        $this->operator->timeOffs()->whereKey($timeOffId)->delete();

        unset($this->timeOffs);
    }

    public function render(): mixed
    {
        return view('livewire.admin.edit-operator')->title($this->operator->name);
    }
}
