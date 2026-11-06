<?php

namespace App\Http\Controllers;

use App\Enums\Weekday;
use App\Models\Operator;
use App\Models\Service;
use App\Models\WorkingHour;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * The public landing page of the barbershop: price list, opening hours and barbers.
     */
    public function __invoke(): View
    {
        return view('welcome', [
            'services' => Service::query()->where('is_active', true)->orderBy('price_cents')->get(),
            'barbers' => Operator::query()
                ->where('is_active', true)
                ->with(['services' => fn ($query) => $query->where('is_active', true)->orderBy('price_cents')])
                ->orderBy('name')
                ->get(),
            'openingHours' => $this->openingHours(),
        ]);
    }

    /**
     * The shop is open whenever at least one active barber works, so the
     * barbers' intervals are merged day by day. An empty list means closed.
     *
     * @return array<int, array{day: string, intervals: list<string>}>
     */
    private function openingHours(): array
    {
        $hoursByDay = WorkingHour::query()
            ->whereRelation('operator', 'is_active', true)
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (WorkingHour $hour): int => $hour->weekday->value);

        $openingHours = [];

        foreach (Weekday::cases() as $weekday) {
            /** @var list<array{string, string}> $merged */
            $merged = [];

            foreach ($hoursByDay->get($weekday->value, []) as $hour) {
                $last = array_key_last($merged);

                if ($last !== null && $hour->start_time <= $merged[$last][1]) {
                    $merged[$last][1] = max($merged[$last][1], $hour->end_time);
                } else {
                    $merged[] = [$hour->start_time, $hour->end_time];
                }
            }

            $openingHours[] = [
                'day' => $weekday->label(),
                'intervals' => array_map(fn (array $interval): string => substr($interval[0], 0, 5).'–'.substr($interval[1], 0, 5), $merged),
            ];
        }

        return $openingHours;
    }
}
