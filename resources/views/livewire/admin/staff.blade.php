<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Staff') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Manage your team and the services each member provides') }}</flux:subheading>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New staff member') }}</flux:button>
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

    @if ($staff->isEmpty())
        <flux:callout icon="users">
            <flux:callout.heading>{{ __('No staff found') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Adjust the filter or add a new staff member.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Services') }}</flux:table.column>
                <flux:table.column>{{ __('Commission') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($staff as $member)
                    <flux:table.row :key="'staff-'.$member->id">
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-2">
                                <flux:avatar
                                    size="xs"
                                    :name="$member->name"
                                    @if ($member->photo) src="{{ Storage::disk('public')->url($member->photo) }}" @endif
                                />
                                {{ $member->name }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $member->phone ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $member->services_count }}</flux:table.cell>
                        <flux:table.cell>{{ $member->commission_percent }}%</flux:table.cell>
                        <flux:table.cell>
                            @if ($member->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $member->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="delete({{ $member->id }})"
                                    wire:confirm="{{ __('Delete this staff member?') }}"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model.self="showForm" class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit staff member') : __('New staff member') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" type="text" required />

            <flux:input wire:model="phone" :label="__('Phone')" type="tel" />

            <flux:input
                wire:model="commission_percent"
                :label="__('Commission (%)')"
                type="number"
                step="0.01"
                min="0"
                max="100"
                required
            />

            <flux:textarea wire:model="bio" :label="__('Bio')" rows="2" />

            <flux:input type="file" wire:model="photo" :label="__('Photo')" accept="image/*" />

            <div>
                <flux:label>{{ __('Services provided') }}</flux:label>

                @if ($services->isEmpty())
                    <flux:text class="mt-2">{{ __('No active services yet. Create services first.') }}</flux:text>
                @else
                    <flux:checkbox.group wire:model="service_ids" class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($services as $service)
                            <flux:checkbox value="{{ $service->id }}" :label="$service->name" />
                        @endforeach
                    </flux:checkbox.group>
                @endif
            </div>

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
