<?php

namespace App\Livewire\Admin;

use App\Actions\Services\SaveService;
use App\Concerns\ServiceValidationRules;
use App\Models\Service;
use App\Models\ServiceCategory;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Services')]
class Services extends Component
{
    use ServiceValidationRules;

    public ?int $editingId = null;

    public bool $showForm = false;

    /** Filter by activation status: all | active | inactive. */
    public string $statusFilter = 'all';

    public ?string $category_id = null;

    public string $name = '';

    public int $duration_minutes = 30;

    public string $price = '0';

    public ?string $description = null;

    public string $color = '#6366F1';

    public bool $is_active = true;

    /**
     * Open the create form.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    /**
     * Open the edit form for an existing service.
     */
    public function edit(Service $service): void
    {
        $this->editingId = $service->id;
        $this->category_id = $service->category_id ? (string) $service->category_id : null;
        $this->name = $service->name;
        $this->duration_minutes = $service->duration_minutes;
        $this->price = (string) $service->price;
        $this->description = $service->description;
        $this->color = $service->color ?? '#6366F1';
        $this->is_active = $service->is_active;
        $this->showForm = true;
    }

    /**
     * Validate and persist the service.
     */
    public function save(SaveService $action): void
    {
        $data = $this->validate($this->serviceRules());

        $service = $this->editingId
            ? Service::query()->findOrFail($this->editingId)
            : null;

        $action->handle($service, $data);

        $this->showForm = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Service saved.'));
    }

    /**
     * Soft-delete a service (history is preserved).
     */
    public function delete(Service $service): void
    {
        $service->delete();

        Flux::toast(variant: 'success', text: __('Service deleted.'));
    }

    /**
     * Reset the form to its default state.
     */
    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->category_id = null;
        $this->name = '';
        $this->duration_minutes = 30;
        $this->price = '0';
        $this->description = null;
        $this->color = '#6366F1';
        $this->is_active = true;
        $this->resetValidation();
    }

    /**
     * Categories available for assignment / grouping.
     *
     * @return Collection<int, ServiceCategory>
     */
    protected function categories(): Collection
    {
        return ServiceCategory::query()->ordered()->get();
    }

    /**
     * Services matching the active status filter, grouped by category id.
     *
     * @return Collection<int, Service>
     */
    protected function services(): Collection
    {
        return Service::query()
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get();
    }

    public function render(): mixed
    {
        return view('livewire.admin.services', [
            'categories' => $this->categories(),
            'services' => $this->services()->groupBy('category_id'),
        ]);
    }
}
