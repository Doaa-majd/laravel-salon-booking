<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Services') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Manage the services your salon offers') }}</flux:subheading>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New service') }}</flux:button>
        </div>
        <flux:separator variant="subtle" class="mt-6" />
    </div>

    <div class="mb-6 max-w-xs">
        <flux:select wire:model.live="statusFilter" :label="__('Status')">
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active only') }}</flux:select.option>
            <flux:select.option value="inactive">{{ __('Inactive only') }}</flux:select.option>
        </flux:select>
    </div>

    @php($uncategorised = $services->get('') ?? $services->get(null))

    @if ($services->isEmpty())
        <flux:callout icon="sparkles">
            <flux:callout.heading>{{ __('No services found') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Adjust the filter or create a new service.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="space-y-8">
            @foreach ($categories as $category)
                @php($rows = $services->get($category->id))
                @if ($rows)
                    <div>
                        <flux:heading size="lg" class="mb-3">{{ $category->name }}</flux:heading>
                        @include('livewire.admin.partials.service-rows', ['rows' => $rows])
                    </div>
                @endif
            @endforeach

            @if ($uncategorised)
                <div>
                    <flux:heading size="lg" class="mb-3">{{ __('Uncategorised') }}</flux:heading>
                    @include('livewire.admin.partials.service-rows', ['rows' => $uncategorised])
                </div>
            @endif
        </div>
    @endif

    <flux:modal wire:model.self="showForm" class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit service') : __('New service') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" type="text" required />

            <flux:select wire:model="category_id" :label="__('Category')" :placeholder="__('Uncategorised')">
                <flux:select.option value="">{{ __('Uncategorised') }}</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input
                    wire:model="duration_minutes"
                    :label="__('Duration (minutes)')"
                    type="number"
                    min="1"
                    required
                />

                <flux:input
                    wire:model="price"
                    :label="__('Price')"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                />
            </div>

            <flux:input wire:model="color" :label="__('Color')" type="color" />

            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

            <flux:switch wire:model="is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
