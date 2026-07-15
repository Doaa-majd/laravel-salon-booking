<?php

namespace App\Livewire\Admin;

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\RescheduleAppointment;
use App\Concerns\AppointmentValidationRules;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff as StaffModel;
use App\Services\AvailabilityService;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The operational calendar (analysis doc §6): a day or week view of appointments
 * as colored blocks, with click-to-open detail, create-from-an-empty-slot, and
 * server-side reschedule. Rescheduling and creation both run through the
 * authoritative booking machinery (RescheduleAppointment / BookAppointment), so
 * a frontend-proposed time is always re-checked under a lock (ADR-0014) — the
 * client is never trusted, and drag-drop degrades to a slot picker (ADR-0016).
 *
 * Admins see every staff member; a staff-role user sees only their own
 * appointments, scoped by their linked staff profile (AppointmentPolicy).
 */
#[Layout('layouts.app')]
#[Title('Calendar')]
class Calendar extends Component
{
    use AppointmentValidationRules;

    /** day | week */
    #[Url]
    public string $viewMode = 'week';

    /** Anchor date (salon-local Y-m-d). */
    #[Url]
    public string $date = '';

    // Create form (mirrors the manual booking flow).
    public bool $showForm = false;

    public ?int $form_client_id = null;

    /** @var array<int, int> */
    public array $form_service_ids = [];

    public ?int $form_staff_id = null;

    public string $form_date = '';

    public string $form_time = '';

    public string $form_status = 'confirmed';

    public ?string $form_notes = null;

    // Reschedule form.
    public bool $showReschedule = false;

    public ?int $reschedule_id = null;

    public ?int $reschedule_staff_id = null;

    public string $reschedule_date = '';

    public string $reschedule_time = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Appointment::class);

        if ($this->date === '') {
            $this->date = Carbon::now(Setting::current()->timezone)->toDateString();
        }
    }

    /**
     * Clearing selections that invalidate a chosen slot keeps the forms honest.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['form_service_ids', 'form_staff_id', 'form_date'], true)) {
            $this->form_time = '';
        }

        if (in_array($property, ['reschedule_staff_id', 'reschedule_date'], true)) {
            $this->reschedule_time = '';
        }
    }

    public function setView(string $mode): void
    {
        $this->viewMode = in_array($mode, ['day', 'week'], true) ? $mode : 'week';
    }

    public function today(): void
    {
        $this->date = Carbon::now(Setting::current()->timezone)->toDateString();
    }

    public function previous(): void
    {
        $this->date = $this->anchor()->sub($this->viewMode === 'day' ? '1 day' : '1 week')->toDateString();
    }

    public function next(): void
    {
        $this->date = $this->anchor()->add($this->viewMode === 'day' ? '1 day' : '1 week')->toDateString();
    }

    /**
     * Open the create form pre-filled for a staff member and date (empty slot).
     */
    public function createAt(?int $staffId, string $date): void
    {
        $this->resetForm();
        $this->form_staff_id = $staffId;
        $this->form_date = $date;
        $this->showForm = true;
    }

    /**
     * Book a manual appointment from the calendar via the authoritative action.
     */
    public function save(BookAppointment $action): void
    {
        $data = $this->validate($this->appointmentRules());

        try {
            $action->handle([
                'client_id' => (int) $data['form_client_id'],
                'service_ids' => array_map('intval', $data['form_service_ids']),
                'staff_id' => $this->form_staff_id !== null ? (int) $this->form_staff_id : null,
                'start' => $data['form_date'].' '.$data['form_time'],
                'status' => AppointmentStatus::from($data['form_status']),
                'source' => AppointmentSource::Manual,
                'notes' => $data['form_notes'] ?? null,
            ]);
        } catch (SlotUnavailableException) {
            $this->addError('form_time', __('That time is no longer available. Please pick another.'));

            return;
        }

        $this->showForm = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Appointment booked.'));
    }

    /**
     * Open the reschedule form for one of the visible appointments.
     */
    public function openReschedule(int $appointmentId): void
    {
        $appointment = $this->appointments()->firstWhere('id', $appointmentId);

        if ($appointment === null) {
            return;
        }

        $this->reschedule_id = $appointment->id;
        $this->reschedule_staff_id = $appointment->staff_id;
        $this->reschedule_date = $appointment->datetime_start->format('Y-m-d');
        $this->reschedule_time = '';
        $this->resetValidation();
        $this->showReschedule = true;
    }

    /**
     * Move the appointment to the chosen slot, re-checking availability.
     */
    public function reschedule(RescheduleAppointment $action): void
    {
        $this->validate([
            'reschedule_id' => ['required', 'integer'],
            'reschedule_date' => ['required', 'date'],
            'reschedule_time' => ['required'],
        ]);

        $appointment = $this->appointments()->firstWhere('id', $this->reschedule_id);

        if ($appointment === null) {
            $this->showReschedule = false;

            return;
        }

        try {
            $action->handle(
                $appointment,
                $this->reschedule_date.' '.$this->reschedule_time,
                $this->reschedule_staff_id !== null ? (int) $this->reschedule_staff_id : null,
            );
        } catch (SlotUnavailableException) {
            $this->addError('reschedule_time', __('That time is no longer available. Please pick another.'));

            return;
        }

        $this->showReschedule = false;
        $this->reschedule_id = null;

        Flux::toast(variant: 'success', text: __('Appointment rescheduled.'));
    }

    /**
     * Reset the create form to its default state.
     */
    protected function resetForm(): void
    {
        $this->form_client_id = null;
        $this->form_service_ids = [];
        $this->form_staff_id = null;
        $this->form_date = '';
        $this->form_time = '';
        $this->form_status = 'confirmed';
        $this->form_notes = null;
        $this->resetValidation();
    }

    /**
     * The anchor date as a salon-local Carbon at midnight.
     */
    protected function anchor(): Carbon
    {
        return Carbon::parse($this->date, Setting::current()->timezone)->startOfDay();
    }

    /**
     * The dates shown by the current view (one for day, seven for week — the
     * week starts on the salon's configured week_start day).
     *
     * @return Collection<int, Carbon>
     */
    protected function days(): Collection
    {
        $anchor = $this->anchor();

        if ($this->viewMode === 'day') {
            return collect([$anchor]);
        }

        $weekStart = Setting::current()->week_start;
        $offset = ($anchor->dayOfWeek - $weekStart + 7) % 7;
        $start = $anchor->copy()->subDays($offset);

        return collect(range(0, 6))->map(fn (int $i): Carbon => $start->copy()->addDays($i));
    }

    /**
     * Staff ids the current user may see (null = all, for admins).
     *
     * @return array<int, int>|null
     */
    protected function visibleStaffIds(): ?array
    {
        $user = auth()->user();

        if ($user !== null && $user->isStaff() && $user->staff_id !== null) {
            return [$user->staff_id];
        }

        return null;
    }

    /**
     * Non-cancelled appointments in the visible range, scoped to the viewer.
     *
     * @return Collection<int, Appointment>
     */
    protected function appointments(): Collection
    {
        $days = $this->days();
        $staffIds = $this->visibleStaffIds();

        return Appointment::query()
            ->with(['client', 'staff', 'services'])
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->whereDate('datetime_start', '>=', $days->first()->toDateString())
            ->whereDate('datetime_start', '<=', $days->last()->toDateString())
            ->when($staffIds !== null, fn ($query) => $query->whereIn('staff_id', $staffIds))
            ->orderBy('datetime_start')
            ->get();
    }

    /**
     * Staff shown as columns (day view) / offered in the create form. Admins get
     * every active staff; a staff user gets only their own profile.
     *
     * @return Collection<int, StaffModel>
     */
    protected function columnStaff(): Collection
    {
        $staffIds = $this->visibleStaffIds();

        return StaffModel::query()
            ->active()
            ->when($staffIds !== null, fn ($query) => $query->whereIn('id', $staffIds))
            ->orderBy('name')
            ->get();
    }

    /**
     * Active staff who provide all selected services (create-form candidates).
     *
     * @return Collection<int, StaffModel>
     */
    protected function staffOptions(): Collection
    {
        return StaffModel::query()
            ->active()
            ->when(
                $this->form_service_ids !== [],
                fn ($query) => $query->providesAllServices(array_map('intval', $this->form_service_ids)),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * Available start slots for the create form's current selection.
     *
     * @return array<int, array{time: string, datetime: string, staff_id: int, staff_name: string}>
     */
    protected function createSlots(): array
    {
        if ($this->form_service_ids === [] || $this->form_date === '') {
            return [];
        }

        return app(AvailabilityService::class)->availableSlots(
            array_map('intval', $this->form_service_ids),
            $this->form_staff_id !== null ? (int) $this->form_staff_id : null,
            $this->form_date,
        );
    }

    /**
     * Available slots for the appointment being rescheduled — its own current
     * interval is excluded so it never blocks its new time.
     *
     * @return array<int, array{time: string, datetime: string, staff_id: int, staff_name: string}>
     */
    protected function rescheduleSlots(): array
    {
        if ($this->reschedule_id === null || $this->reschedule_date === '') {
            return [];
        }

        $appointment = Appointment::query()->with('services')->find($this->reschedule_id);

        if ($appointment === null) {
            return [];
        }

        $serviceIds = $appointment->services->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return app(AvailabilityService::class)->availableSlots(
            $serviceIds,
            $this->reschedule_staff_id !== null ? (int) $this->reschedule_staff_id : null,
            $this->reschedule_date,
            $appointment->id,
        );
    }

    public function render(): mixed
    {
        $days = $this->days();

        // Group the range's appointments by date for the week view.
        $appointments = $this->appointments();

        return view('livewire.admin.calendar', [
            'days' => $days,
            'appointments' => $appointments,
            'byDate' => $appointments->groupBy(fn (Appointment $a): string => $a->datetime_start->format('Y-m-d')),
            'columnStaff' => $this->columnStaff(),
            'clients' => Client::query()->orderBy('name')->get(),
            'services' => Service::query()->active()->orderBy('name')->get(),
            'staffOptions' => $this->staffOptions(),
            'createSlots' => $this->createSlots(),
            'rescheduleSlots' => $this->rescheduleSlots(),
            'rangeLabel' => $this->viewMode === 'day'
                ? $days->first()->format('Y-m-d')
                : $days->first()->format('Y-m-d').' — '.$days->last()->format('Y-m-d'),
        ]);
    }
}
