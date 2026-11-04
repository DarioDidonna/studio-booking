<?php

namespace Database\Seeders;

use App\Enums\Weekday;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with a demo studio.
     * Every account uses the password "password". Bookings go through
     * BookingService, so they get a status history and a reminder like real ones.
     */
    public function run(BookingService $bookingService): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin Studio',
            'email' => 'admin@example.com',
        ]);

        $customer = User::factory()->create([
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
        ]);

        User::factory(5)->create();

        $haircut = Service::factory()->create(['name' => 'Taglio', 'duration_minutes' => 30, 'price_cents' => 2000]);
        $colour = Service::factory()->create(['name' => 'Colore', 'duration_minutes' => 90, 'price_cents' => 6000]);
        $massage = Service::factory()->create(['name' => 'Massaggio', 'duration_minutes' => 60, 'price_cents' => 4500]);

        $giulia = Operator::factory()->create([
            'name' => 'Giulia Rossi',
            'user_id' => User::factory()->staff()->create(['name' => 'Giulia Rossi', 'email' => 'giulia@example.com'])->id,
        ]);
        $giulia->services()->attach([$haircut->id, $colour->id]);

        $marco = Operator::factory()->create([
            'name' => 'Marco Bianchi',
            'user_id' => User::factory()->staff()->create(['name' => 'Marco Bianchi', 'email' => 'marco@example.com'])->id,
        ]);
        $marco->services()->attach([$haircut->id, $massage->id]);

        foreach ([$giulia, $marco] as $operator) {
            $this->seedWorkingWeek($operator);
        }

        $giulia->workingHours()->create(['weekday' => Weekday::Saturday, 'start_time' => '09:00:00', 'end_time' => '13:00:00']);

        $holidayStart = Date::parse('first day of +2 months 00:00', config('app.business_timezone'));

        $marco->timeOffs()->create([
            'starts_at' => $holidayStart->utc(),
            'ends_at' => $holidayStart->addWeek()->utc(),
            'reason' => 'Ferie',
        ]);

        $nextMonday = Date::parse('next monday 10:00', config('app.business_timezone'));

        $bookingService->book($customer, $giulia, $haircut, $nextMonday, $customer);
        $bookingService->book($customer, $marco, $massage, $nextMonday->setTime(14, 0), $customer, 'Prima seduta');
    }

    /**
     * Monday to Friday, 9:00-13:00 and 14:00-18:00, with a lunch break.
     */
    private function seedWorkingWeek(Operator $operator): void
    {
        foreach ([Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday, Weekday::Thursday, Weekday::Friday] as $weekday) {
            $operator->workingHours()->createMany([
                ['weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '13:00:00'],
                ['weekday' => $weekday, 'start_time' => '14:00:00', 'end_time' => '18:00:00'],
            ]);
        }
    }
}
