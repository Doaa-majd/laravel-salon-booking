<?php

namespace App\Livewire\Admin;

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\ChangeAppointmentStatus;
use App\Actions\Appointments\SendAppointmentReminder;
use App\Concerns\AppointmentValidationRules;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff as StaffModel;
use App\Services\AvailabilityService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Appointments')]
class Appointments extends Component
{
    use AppointmentValidationRules;

    // Filters.
    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $staffFilter = '';

    #[Url]
    public string $phoneSearch = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    // Booking form.
    public bool $showForm = false;

    public ?int $form_client_id = null;

    /** @var array<int, int> */
    public array $form_service_ids = [];

    public ?int $form_staff_id = null;

    public string $form_date = '';

    public string $form_time = '';

    public string $form_status = 'pending';

    public ?string $form_notes = null;

    // Notes editor.
    public bool $showEdit = false;

    public ?int $editingId = null;

    public ?string $edit_notes = null;

    // WhatsApp reminder.
    public bool $showReminder = false;

    public ?string $reminderUrl = null;

    /**
     * Open the manual booking form.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->form_date = now()->addDay()->toDateString();
        $this->showForm = true;
    }

    /**
     * Clearing selections that invalidate the chosen slot keeps the form honest.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['form_service_ids', 'form_staff_id', 'form_date'], true)) {
            $this->form_time = '';
        }
    }

    /**
     * Validate and book the appointment via the authoritative action.
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
     * Change an appointment's status through the allowed transitions.
     */
    public function changeStatus(Appointment $appointment, string $status, ChangeAppointmentStatus $action): void
    {
        try {
            $action->handle($appointment, AppointmentStatus::from($status));
        } catch (InvalidStatusTransitionException) {
            Flux::toast(variant: 'danger', text: __('That status change is not allowed.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Appointment status updated.'));
    }

    /**
     * Build a WhatsApp reminder link for the appointment's client and log it.
     */
    public function sendReminder(Appointment $appointment, SendAppointmentReminder $action): void
    {
        $this->reminderUrl = $action->handle($appointment);
        $this->showReminder = true;

        Flux::toast(variant: 'success', text: __('Reminder logged. Open WhatsApp to send it.'));
    }

    /**
     * Open the notes editor for an appointment.
     */
    public function edit(Appointment $appointment): void
    {
        $this->editingId = $appointment->id;
        $this->edit_notes = $appointment->notes;
        $this->showEdit = true;
    }

    /**
     * Persist edited notes.
     */
    public function updateNotes(): void
    {
        $appointment = Appointment::query()->findOrFail($this->editingId);
        $appointment->update(['notes' => $this->edit_notes ?: null]);

        $this->showEdit = false;
        $this->editingId = null;
        $this->edit_notes = null;

        Flux::toast(variant: 'success', text: __('Appointment updated.'));
    }

    /**
     * Soft-delete an appointment (never hard-deleted — history is preserved).
     */
    public function delete(Appointment $appointment): void
    {
        $appointment->delete();

        Flux::toast(variant: 'success', text: __('Appointment deleted.'));
    }

    /**
     * Reset the booking form to its default state.
     */
    protected function resetForm(): void
    {
        $this->form_client_id = null;
        $this->form_service_ids = [];
        $this->form_staff_id = null;
        $this->form_date = '';
        $this->form_time = '';
        $this->form_status = 'pending';
        $this->form_notes = null;
        $this->resetValidation();
    }

    /**
     * Filtered appointment rows with their related client, staff, and services.
     *
     * @return Collection<int, Appointment>
     */
    protected function appointments(): Collection
    {
        return Appointment::query()
            ->with(['client', 'staff', 'services'])
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->staffFilter !== '', fn ($query) => $query->where('staff_id', (int) $this->staffFilter))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('datetime_start', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('datetime_start', '<=', $this->dateTo))
            ->when($this->phoneSearch !== '', fn ($query) => $query->whereHas(
                'client',
                fn ($client) => $client->where('phone', 'like', '%'.trim($this->phoneSearch).'%'),
            ))
            ->orderByDesc('datetime_start')
            ->get();
    }

    /**
     * Active staff who provide all currently selected services (candidates for
     * the booking). With no services chosen yet, all active staff are offered.
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
     * Available start-time slots for the current booking selection.
     *
     * @return array<int, array{time: string, datetime: string, staff_id: int, staff_name: string}>
     */
    protected function availableSlots(): array
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

    public function render(): mixed
    {
        return view('livewire.admin.appointments', [
            'appointments' => $this->appointments(),
            'clients' => Client::query()->orderBy('name')->get(),
            'services' => Service::query()->active()->orderBy('name')->get(),
            'staffOptions' => $this->staffOptions(),
            'allStaff' => StaffModel::query()->orderBy('name')->get(),
            'slots' => $this->availableSlots(),
            'statuses' => AppointmentStatus::cases(),
        ]);
    }
}
