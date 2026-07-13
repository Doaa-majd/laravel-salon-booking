<?php

namespace App\Livewire\Public;

use App\Actions\Appointments\BookAppointment;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff as StaffModel;
use App\Rules\PhoneNumber;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The public, guest-facing booking wizard (mobile-first, RTL).
 *
 * Five steps: (1) pick services, (2) pick a staff member or "any", (3) pick a
 * date and an available time, (4) enter contact details, (5) review & confirm.
 * The component is UI orchestration only — the availability engine and the
 * authoritative, locked, re-checked write both live in dedicated classes
 * (ADR-0004/ADR-0014). Frontend availability is never trusted: the chosen slot
 * is re-validated inside BookAppointment on submit.
 */
#[Layout('layouts.public')]
#[Title('Book an appointment')]
class BookingWizard extends Component
{
    public int $step = 1;

    /** @var array<int, int> */
    public array $serviceIds = [];

    /** A specific staff member, or null for "any available". */
    public ?int $staffId = null;

    public string $date = '';

    public string $time = '';

    public string $name = '';

    public string $phone = '';

    public ?string $email = null;

    public function mount(): void
    {
        $this->date = Carbon::now(Setting::current()->timezone)->toDateString();
    }

    /**
     * Selecting different services/staff/date invalidates the chosen time; and
     * changing services may disqualify the chosen staff member.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['serviceIds', 'staffId', 'date'], true)) {
            $this->time = '';
        }

        if ($property === 'serviceIds' && $this->staffId !== null && ! $this->staffQualifies()) {
            $this->staffId = null;
        }
    }

    /**
     * Advance to the next step after validating the current one.
     */
    public function next(): void
    {
        match ($this->step) {
            1 => $this->validate([
                'serviceIds' => ['required', 'array', 'min:1'],
                'serviceIds.*' => [Rule::exists('services', 'id')],
            ], attributes: ['serviceIds' => __('services')]),
            3 => $this->validate([
                'date' => ['required', 'date'],
                'time' => ['required', 'string'],
            ]),
            4 => $this->validateClient(),
            default => null,
        };

        if ($this->step < 5) {
            $this->step++;
        }
    }

    /**
     * Go back to the previous step.
     */
    public function back(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    /**
     * Pick an available start time.
     */
    public function selectSlot(string $time): void
    {
        $this->time = $time;
    }

    /**
     * Confirm the booking: re-validate everything, then write through the
     * authoritative action (source=online, status=pending). A slot that filled
     * between selection and submit is rejected and the user is sent back to
     * re-pick a time.
     */
    public function submit(BookAppointment $action): void
    {
        $this->validate([
            'serviceIds' => ['required', 'array', 'min:1'],
            'serviceIds.*' => [Rule::exists('services', 'id')],
            'staffId' => ['nullable', Rule::exists('staff', 'id')],
            'date' => ['required', 'date'],
            'time' => ['required', 'string'],
        ]);
        $this->validateClient();

        try {
            $appointment = $action->handle([
                'service_ids' => array_map('intval', $this->serviceIds),
                'staff_id' => $this->staffId,
                'start' => $this->date.' '.$this->time,
                'client' => ['name' => $this->name, 'phone' => $this->phone, 'email' => $this->email],
                'source' => AppointmentSource::Online,
                'status' => AppointmentStatus::Pending,
            ]);
        } catch (SlotUnavailableException) {
            $this->time = '';
            $this->step = 3;
            $this->addError('time', __('That time is no longer available. Please pick another.'));

            return;
        }

        $this->redirect(route('book.confirmed', $appointment), navigate: true);
    }

    /**
     * Normalize the phone toward canonical form, then validate contact details.
     */
    protected function validateClient(): void
    {
        $this->phone = PhoneNumber::normalize($this->phone);

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', new PhoneNumber],
            'email' => ['nullable', 'email', 'max:150'],
        ]);
    }

    /**
     * Whether the currently selected staff member provides every chosen service.
     */
    protected function staffQualifies(): bool
    {
        return StaffModel::query()
            ->active()
            ->providesAllServices(array_map('intval', $this->serviceIds))
            ->whereKey($this->staffId)
            ->exists();
    }

    /**
     * Active service categories (with their active services) plus a synthetic
     * "uncategorized" bucket, for the service-selection step.
     *
     * @return Collection<int, ServiceCategory>
     */
    protected function categories(): Collection
    {
        $categories = ServiceCategory::query()
            ->active()
            ->ordered()
            ->with(['services' => fn ($query) => $query->active()->orderBy('name')])
            ->get()
            ->filter(fn (ServiceCategory $category): bool => $category->services->isNotEmpty())
            ->values();

        $uncategorized = Service::query()->active()->whereNull('category_id')->orderBy('name')->get();

        if ($uncategorized->isNotEmpty()) {
            $bucket = new ServiceCategory(['name' => __('Other')]);
            $bucket->setRelation('services', $uncategorized);
            $categories->push($bucket);
        }

        return $categories;
    }

    /**
     * Active staff who provide all selected services (the "pick a staff" step).
     *
     * @return Collection<int, StaffModel>
     */
    protected function staffOptions(): Collection
    {
        if ($this->serviceIds === []) {
            return new Collection;
        }

        return StaffModel::query()
            ->active()
            ->providesAllServices(array_map('intval', $this->serviceIds))
            ->orderBy('name')
            ->get();
    }

    /**
     * Available start-time slots for the current selection.
     *
     * @return array<int, array{time: string, datetime: string, staff_id: int, staff_name: string}>
     */
    protected function slots(): array
    {
        if ($this->serviceIds === [] || $this->date === '') {
            return [];
        }

        return app(AvailabilityService::class)->availableSlots(
            array_map('intval', $this->serviceIds),
            $this->staffId,
            $this->date,
        );
    }

    /**
     * The selected active services (for the review step and totals).
     *
     * @return Collection<int, Service>
     */
    protected function selectedServices(): Collection
    {
        if ($this->serviceIds === []) {
            return new Collection;
        }

        return Service::query()->active()->whereKey(array_map('intval', $this->serviceIds))->get();
    }

    public function render(): mixed
    {
        $selected = $this->selectedServices();

        return view('livewire.public.booking-wizard', [
            'categories' => $this->categories(),
            'staffOptions' => $this->staffOptions(),
            'slots' => $this->slots(),
            'selectedServices' => $selected,
            'totalDuration' => (int) $selected->sum('duration_minutes'),
            'totalPrice' => $selected->sum(fn (Service $service): float => (float) $service->price),
            'setting' => Setting::current(),
        ]);
    }
}
