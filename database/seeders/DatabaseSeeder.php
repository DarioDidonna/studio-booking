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
     * Seed the application's database with a demo barbershop.
     * Every account uses the password "password". Bookings go through
     * BookingService, so they get a status history and a reminder like real ones.
     */
    public function run(BookingService $bookingService): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin Barberia',
            'email' => 'admin@example.com',
        ]);

        $customer = User::factory()->create([
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
        ]);

        User::factory(5)->create();

        $haircut = Service::factory()->create(['name' => 'Taglio classico', 'description' => 'Forbici e macchinetta, shampoo e styling.', 'duration_minutes' => 30, 'price_cents' => 2000]);
        $beard = Service::factory()->create(['name' => 'Regolazione barba', 'description' => 'Rifinitura col rasoio e olio da barba.', 'duration_minutes' => 20, 'price_cents' => 1200]);
        $haircutAndBeard = Service::factory()->create(['name' => 'Taglio e barba', 'description' => 'Il servizio completo, con panno caldo finale.', 'duration_minutes' => 50, 'price_cents' => 3000]);
        $shave = Service::factory()->create(['name' => 'Rasatura tradizionale', 'description' => 'Panno caldo, sapone a pennello e rasoio a mano libera.', 'duration_minutes' => 30, 'price_cents' => 1800]);
        $kidsHaircut = Service::factory()->create(['name' => 'Taglio bambino', 'description' => 'Fino a 12 anni.', 'duration_minutes' => 20, 'price_cents' => 1400]);

        $giulia = Operator::factory()->create([
            'name' => 'Giulia Rossi',
            'user_id' => User::factory()->staff()->create(['name' => 'Giulia Rossi', 'email' => 'giulia@example.com'])->id,
        ]);
        $giulia->services()->attach([$haircut->id, $beard->id, $haircutAndBeard->id, $kidsHaircut->id]);

        $marco = Operator::factory()->create([
            'name' => 'Marco Bianchi',
            'user_id' => User::factory()->staff()->create(['name' => 'Marco Bianchi', 'email' => 'marco@example.com'])->id,
        ]);
        $marco->services()->attach([$haircut->id, $beard->id, $haircutAndBeard->id, $shave->id]);

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
        $bookingService->book($customer, $marco, $shave, $nextMonday->setTime(14, 0), $customer, 'Prima rasatura col rasoio');
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
