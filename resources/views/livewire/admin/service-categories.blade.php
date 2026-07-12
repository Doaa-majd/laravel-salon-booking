<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Service categories') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Group your services and control their order') }}</flux:subheading>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New category') }}</flux:button>
        </div>
        <flux:separator variant="subtle" class="mt-6" />
    </div>

    @if ($categories->isEmpty())
        <flux:callout icon="folder-open">
            <flux:callout.heading>{{ __('No categories yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Create your first category to start organising services.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Order') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Services') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($categories as $index => $category)
                    <flux:table.row :key="'category-'.$category->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-1">
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="chevron-up"
                                    :disabled="$index === 0"
                                    wire:click="moveUp({{ $category->id }})"
                                />
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="chevron-down"
                                    :disabled="$index === $categories->count() - 1"
                                    wire:click="moveDown({{ $category->id }})"
                                />
                            </div>
                        </flux:table.cell>
                        <flux:table.cell variant="strong">{{ $category->name }}</flux:table.cell>
                        <flux:table.cell>{{ $category->services_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($category->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $category->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="delete({{ $category->id }})"
                                    wire:confirm="{{ __('Delete this category? Its services will be kept and left uncategorised.') }}"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model.self="showForm" class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit category') : __('New category') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" type="text" required />

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
