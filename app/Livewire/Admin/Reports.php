<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Services\ReportingService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The admin reports screen (analysis doc §11): most-requested services, staff
 * performance and commission, and no-shows over a chosen date range. Thin by
 * design ([ADR-0004]) — all aggregation lives in ReportingService, which reads
 * only immutable snapshots ([ADR-0006]). Defaults to the current salon-local
 * month-to-date.
 */
#[Layout('layouts.app')]
#[Title('Reports')]
class Reports extends Component
{
    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public function mount(): void
    {
        $today = Carbon::now(Setting::current()->timezone);

        if ($this->dateFrom === '') {
            $this->dateFrom = $today->copy()->startOfMonth()->toDateString();
        }

        if ($this->dateTo === '') {
            $this->dateTo = $today->toDateString();
        }
    }

    public function render(ReportingService $reports): mixed
    {
        // Guard against an inverted range so aggregates never silently return empty.
        $from = $this->dateFrom;
        $to = $this->dateTo;

        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return view('livewire.admin.reports', [
            'topServices' => $reports->topServices($from, $to),
            'staffPerformance' => $reports->staffPerformance($from, $to),
            'totals' => $reports->totals($from, $to),
            'currency' => Setting::current()->currency->value,
        ]);
    }
}
