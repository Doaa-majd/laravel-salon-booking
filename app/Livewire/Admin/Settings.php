<?php

namespace App\Livewire\Admin;

use App\Actions\Settings\UpdateSalonSettings;
use App\Concerns\SalonSettingsValidationRules;
use App\Models\Setting;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Salon settings')]
class Settings extends Component
{
    use SalonSettingsValidationRules;
    use WithFileUploads;

    public string $salon_name = '';

    public ?string $primary_color = null;

    public string $currency = 'SAR';

    public string $timezone = 'Asia/Riyadh';

    public int $week_start = 0;

    public ?string $phone = null;

    public ?string $whatsapp_number = null;

    public ?string $address = null;

    public ?string $reminder_template = null;

    public ?string $existing_logo = null;

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    /** @var array<int, array{open: string, close: string, closed: bool}> */
    public array $working_hours = [];

    /**
     * Load the current salon settings into the form.
     */
    public function mount(): void
    {
        $setting = Setting::current();

        $this->salon_name = $setting->salon_name;
        $this->primary_color = $setting->primary_color;
        $this->currency = $setting->currency->value;
        $this->timezone = $setting->timezone;
        $this->week_start = $setting->week_start;
        $this->phone = $setting->phone;
        $this->whatsapp_number = $setting->whatsapp_number;
        $this->address = $setting->address;
        $this->reminder_template = $setting->reminder_template;
        $this->existing_logo = $setting->logo;
        $this->working_hours = $this->mapWorkingHours($setting->working_hours);
    }

    /**
     * Validate and persist the salon settings.
     */
    public function save(UpdateSalonSettings $action): void
    {
        $data = $this->validate($this->salonSettingsRules());

        $this->assertWorkingHoursValid();

        if ($this->logo) {
            $data['logo'] = $this->logo->store('logos', 'public');
        }

        $setting = $action->handle(Setting::current(), $data);

        $this->logo = null;
        $this->existing_logo = $setting->logo;

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    /**
     * Ensure open days close after they open.
     */
    protected function assertWorkingHoursValid(): void
    {
        $errors = [];

        foreach ($this->working_hours as $weekday => $day) {
            if ($day['closed']) {
                continue;
            }

            if ($day['close'] <= $day['open']) {
                $errors["working_hours.{$weekday}.close"] = __('Closing time must be after opening time.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Build a stable 0..6 keyed working-hours structure for the form.
     *
     * @param  array<int|string, array{open?: string|null, close?: string|null, closed?: bool}>  $hours
     * @return array<int, array{open: string, close: string, closed: bool}>
     */
    protected function mapWorkingHours(array $hours): array
    {
        $result = [];

        foreach (range(0, 6) as $weekday) {
            $day = $hours[$weekday] ?? $hours[(string) $weekday] ?? [];

            $result[$weekday] = [
                'open' => (string) ($day['open'] ?? '10:00'),
                'close' => (string) ($day['close'] ?? '22:00'),
                'closed' => (bool) ($day['closed'] ?? false),
            ];
        }

        return $result;
    }

    public function render(): mixed
    {
        return view('livewire.admin.settings');
    }
}
