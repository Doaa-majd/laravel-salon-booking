<?php

namespace Database\Seeders;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\ClientPhotoType;
use App\Enums\Currency;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientPhoto;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Seed a realistic demo dataset for the salon.
     */
    public function run(): void
    {
        $this->seedSettings();
        $this->seedUsers();

        $this->seedCatalog();
        $this->seedStaff();
        $this->seedClients();
    }

    /**
     * Ensure the single salon settings row exists.
     */
    protected function seedSettings(): void
    {
        Setting::query()->firstOrCreate(
            ['id' => 1],
            [
                'salon_name' => 'صالون لمسة جمال',
                'primary_color' => '#1E88E5',
                'currency' => Currency::SAR,
                'timezone' => 'Asia/Riyadh',
                'week_start' => 0,
                'phone' => '+966512345678',
                'whatsapp_number' => '+966512345678',
                'address' => 'الرياض، حي النخيل',
                'working_hours' => $this->defaultWorkingHours(),
                'reminder_template' => 'مرحباً {client}، نذكّرك بموعدك في {salon} يوم {date} الساعة {time}.',
            ],
        );
    }

    /**
     * Seed an admin account and a staff-linked account.
     */
    protected function seedUsers(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Salon Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
            ],
        );
    }

    /**
     * Seed categories and their services.
     */
    protected function seedCatalog(): void
    {
        $hair = ServiceCategory::factory()->create(['name' => 'الشعر', 'sort_order' => 1]);
        $skin = ServiceCategory::factory()->create(['name' => 'البشرة', 'sort_order' => 2]);

        Service::factory()->create(['category_id' => $hair->id, 'name' => 'قص شعر', 'duration_minutes' => 45, 'price' => 120]);
        Service::factory()->create(['category_id' => $hair->id, 'name' => 'صبغة شعر', 'duration_minutes' => 90, 'price' => 350]);
        Service::factory()->create(['category_id' => $hair->id, 'name' => 'تسريحة', 'duration_minutes' => 60, 'price' => 200]);
        Service::factory()->create(['category_id' => $skin->id, 'name' => 'تنظيف بشرة', 'duration_minutes' => 60, 'price' => 250]);
        Service::factory()->create(['category_id' => $skin->id, 'name' => 'ماسك مرطّب', 'duration_minutes' => 30, 'price' => 90]);
        Service::factory()->create(['category_id' => $skin->id, 'name' => 'مكياج', 'duration_minutes' => 60, 'price' => 300]);
    }

    /**
     * Seed staff with weekly schedules, service links, and sample time off.
     */
    protected function seedStaff(): void
    {
        $services = Service::query()->pluck('id')->all();

        $sara = Staff::factory()->create(['name' => 'سارة', 'commission_percent' => 15]);
        $lina = Staff::factory()->create(['name' => 'لينا', 'commission_percent' => 20]);

        $sara->services()->sync($services);
        $lina->services()->sync($services);

        $this->seedWeeklySchedule($sara);
        $this->seedWeeklySchedule($lina);

        // Full-day leave for Lina.
        StaffTimeOff::factory()->create([
            'staff_id' => $lina->id,
            'date' => now()->addWeek()->format('Y-m-d'),
            'reason' => 'إجازة',
        ]);

        // Partial-day leave for Sara (fuels the Day 6 availability subtraction step).
        StaffTimeOff::factory()->partial('13:00', '15:00')->create([
            'staff_id' => $sara->id,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'reason' => 'موعد شخصي',
        ]);
    }

    /**
     * Create a 7-day weekly schedule for a staff member (Friday off).
     */
    protected function seedWeeklySchedule(Staff $staff): void
    {
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            StaffSchedule::factory()->create([
                'staff_id' => $staff->id,
                'weekday' => $weekday,
                'start_time' => '10:00',
                'end_time' => '18:00',
                'is_off' => $weekday === 5,
            ]);
        }
    }

    /**
     * Seed a handful of demo clients, one with a completed appointment and a
     * before/after photo pair so the client profile aggregates render.
     */
    protected function seedClients(): void
    {
        Client::factory()->count(4)->create();

        $client = Client::factory()->create(['name' => 'نورة', 'tags' => ['VIP']]);
        $staff = Staff::query()->firstOrFail();

        /** @var array<int, Service> $services */
        $services = Service::query()->take(2)->get()->all();
        $duration = array_sum(array_map(fn (Service $service): int => $service->duration_minutes, $services));
        $total = array_sum(array_map(fn (Service $service): float => (float) $service->price, $services));

        $start = Carbon::now('Asia/Riyadh')->subWeek()->setTime(11, 0);

        $appointment = Appointment::factory()->create([
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'datetime_start' => $start,
            'datetime_end' => $start->copy()->addMinutes($duration),
            'status' => AppointmentStatus::Completed,
            'source' => AppointmentSource::Manual,
            'price_snapshot' => $total,
        ]);

        foreach ($services as $service) {
            $appointment->appointmentServices()->create([
                'service_id' => $service->id,
                'price' => $service->price,
                'duration_minutes' => $service->duration_minutes,
            ]);
        }

        ClientPhoto::factory()->create([
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
            'type' => ClientPhotoType::Before,
            'taken_at' => $start,
        ]);

        ClientPhoto::factory()->create([
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
            'type' => ClientPhotoType::After,
            'taken_at' => $start->copy()->addMinutes($duration),
        ]);
    }

    /**
     * Build the default 7-day working-hours structure (0 = Sunday, Friday closed).
     *
     * @return array<int, array{open: string, close: string, closed: bool}>
     */
    protected function defaultWorkingHours(): array
    {
        $hours = [];

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $hours[$weekday] = [
                'open' => '10:00',
                'close' => '22:00',
                'closed' => $weekday === 5,
            ];
        }

        return $hours;
    }
}
