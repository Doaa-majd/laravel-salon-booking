<?php

namespace App\Livewire\Admin;

use App\Actions\Staff\SaveStaff;
use App\Concerns\StaffValidationRules;
use App\Models\Service;
use App\Models\Staff as StaffModel;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Staff')]
class Staff extends Component
{
    use StaffValidationRules;
    use WithFileUploads;

    public ?int $editingId = null;

    public bool $showForm = false;

    /** Filter by activation status: all | active | inactive. */
    public string $statusFilter = 'all';

    public string $name = '';

    public ?string $phone = null;

    public ?string $bio = null;

    public string $commission_percent = '0';

    public bool $is_active = true;

    public ?string $existing_photo = null;

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    /** @var array<int, int> */
    public array $service_ids = [];

    /**
     * Open the create form.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    /**
     * Open the edit form for an existing staff member.
     */
    public function edit(StaffModel $staff): void
    {
        $this->editingId = $staff->id;
        $this->name = $staff->name;
        $this->phone = $staff->phone;
        $this->bio = $staff->bio;
        $this->commission_percent = (string) $staff->commission_percent;
        $this->is_active = $staff->is_active;
        $this->existing_photo = $staff->photo;
        $this->photo = null;
        $this->service_ids = $staff->services()->pluck('services.id')->map(fn ($id): int => (int) $id)->all();
        $this->showForm = true;
    }

    /**
     * Validate and persist the staff member.
     */
    public function save(SaveStaff $action): void
    {
        $data = $this->validate($this->staffRules());

        if ($this->photo) {
            $data['photo'] = $this->photo->store('staff', 'public');
        }

        $staff = $this->editingId
            ? StaffModel::query()->findOrFail($this->editingId)
            : null;

        $action->handle($staff, $data);

        $this->showForm = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Staff member saved.'));
    }

    /**
     * Soft-delete a staff member (history is preserved).
     */
    public function delete(StaffModel $staff): void
    {
        $staff->delete();

        Flux::toast(variant: 'success', text: __('Staff member deleted.'));
    }

    /**
     * Reset the form to its default state.
     */
    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->phone = null;
        $this->bio = null;
        $this->commission_percent = '0';
        $this->is_active = true;
        $this->existing_photo = null;
        $this->photo = null;
        $this->service_ids = [];
        $this->resetValidation();
    }

    /**
     * Active services available to assign to staff, grouped for display.
     *
     * @return Collection<int, Service>
     */
    protected function services(): Collection
    {
        return Service::query()->active()->orderBy('name')->get();
    }

    /**
     * Staff matching the active status filter, with service counts.
     *
     * @return Collection<int, StaffModel>
     */
    protected function staff(): Collection
    {
        return StaffModel::query()
            ->withCount('services')
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get();
    }

    public function render(): mixed
    {
        return view('livewire.admin.staff', [
            'staff' => $this->staff(),
            'services' => $this->services(),
        ]);
    }
}
