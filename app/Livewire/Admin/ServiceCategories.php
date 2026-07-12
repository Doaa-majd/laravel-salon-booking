<?php

namespace App\Livewire\Admin;

use App\Actions\Services\ReorderServiceCategories;
use App\Actions\Services\SaveServiceCategory;
use App\Concerns\ServiceCategoryValidationRules;
use App\Models\ServiceCategory;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Service categories')]
class ServiceCategories extends Component
{
    use ServiceCategoryValidationRules;

    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

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
     * Open the edit form for an existing category.
     */
    public function edit(ServiceCategory $category): void
    {
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->is_active = $category->is_active;
        $this->showForm = true;
    }

    /**
     * Validate and persist the category.
     */
    public function save(SaveServiceCategory $action): void
    {
        $data = $this->validate($this->serviceCategoryRules());

        $category = $this->editingId
            ? ServiceCategory::query()->findOrFail($this->editingId)
            : null;

        $action->handle($category, $data);

        $this->showForm = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Category saved.'));
    }

    /**
     * Delete a category. Its services are detached (category set to null).
     */
    public function delete(ServiceCategory $category): void
    {
        $category->delete();

        Flux::toast(variant: 'success', text: __('Category deleted.'));
    }

    /**
     * Move a category one position earlier in the sort order.
     */
    public function moveUp(int $id, ReorderServiceCategories $action): void
    {
        $this->move($id, -1, $action);
    }

    /**
     * Move a category one position later in the sort order.
     */
    public function moveDown(int $id, ReorderServiceCategories $action): void
    {
        $this->move($id, 1, $action);
    }

    /**
     * Swap a category with its neighbour and persist the new order.
     */
    protected function move(int $id, int $direction, ReorderServiceCategories $action): void
    {
        /** @var array<int, int> $ids */
        $ids = $this->categories()->pluck('id')->map(fn ($value): int => (int) $value)->all();

        $index = array_search($id, $ids, true);

        if ($index === false) {
            return;
        }

        $target = $index + $direction;

        if ($target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $action->handle($ids);
    }

    /**
     * Reset the form to its default state.
     */
    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    /**
     * The ordered categories with service counts.
     *
     * @return Collection<int, ServiceCategory>
     */
    protected function categories(): Collection
    {
        return ServiceCategory::query()
            ->withCount('services')
            ->ordered()
            ->get();
    }

    public function render(): mixed
    {
        return view('livewire.admin.service-categories', [
            'categories' => $this->categories(),
        ]);
    }
}
