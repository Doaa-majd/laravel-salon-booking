<?php

namespace App\Actions\Settings;

use App\Models\Setting;

class UpdateSalonSettings
{
    /**
     * Persist validated salon settings to the single settings row.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Setting $setting, array $data): Setting
    {
        $setting->fill([
            'salon_name' => $data['salon_name'],
            'primary_color' => $data['primary_color'] ?? null,
            'currency' => $data['currency'],
            'timezone' => $data['timezone'],
            'week_start' => (int) $data['week_start'],
            'phone' => $data['phone'] ?? null,
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'address' => $data['address'] ?? null,
            'reminder_template' => $data['reminder_template'] ?? null,
            'working_hours' => $this->normalizeWorkingHours($data['working_hours'] ?? []),
        ]);

        if (! empty($data['logo'])) {
            $setting->logo = $data['logo'];
        }

        $setting->save();

        return $setting;
    }

    /**
     * Normalize the working-hours grid to a stable 0..6 keyed structure.
     *
     * @param  array<int|string, array{open?: string|null, close?: string|null, closed?: bool}>  $hours
     * @return array<int, array{open: string, close: string, closed: bool}>
     */
    private function normalizeWorkingHours(array $hours): array
    {
        $normalized = [];

        foreach (range(0, 6) as $weekday) {
            $day = $hours[$weekday] ?? $hours[(string) $weekday] ?? [];

            $normalized[$weekday] = [
                'open' => (string) ($day['open'] ?? '10:00'),
                'close' => (string) ($day['close'] ?? '22:00'),
                'closed' => (bool) ($day['closed'] ?? false),
            ];
        }

        return $normalized;
    }
}
