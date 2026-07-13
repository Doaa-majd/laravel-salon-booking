<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Clients') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Manage client records, cosmetic profiles, and photo galleries') }}</flux:subheading>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New client') }}</flux:button>
        </div>
        <flux:separator variant="subtle" class="mt-6" />
    </div>

    <div class="mb-6 max-w-sm">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search by name or phone')"
            :label="__('Search')"
        />
    </div>

    @if ($clients->isEmpty())
        <flux:callout icon="user-group">
            <flux:callout.heading>{{ __('No clients found') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Adjust your search or add a new client.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Visits') }}</flux:table.column>
                <flux:table.column>{{ __('Last visit') }}</flux:table.column>
                <flux:table.column>{{ __('Tags') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($clients as $client)
                    <flux:table.row :key="'client-'.$client->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('admin.clients.show', $client)" wire:navigate>
                                {{ $client->name }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $client->phone }}</flux:table.cell>
                        <flux:table.cell>{{ $client->appointments_count }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $client->last_visit_at ? \Illuminate\Support\Carbon::parse($client->last_visit_at)->format('Y-m-d') : '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($client->tags ?? [] as $tag)
                                    <flux:badge color="blue" size="sm">{{ $tag }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="user" :href="route('admin.clients.show', $client)" wire:navigate>
                                    {{ __('Profile') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $client->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="delete({{ $client->id }})"
                                    wire:confirm="{{ __('Delete this client?') }}"
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
                <flux:heading size="lg">{{ $editingId ? __('Edit client') : __('New client') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" type="text" required />

            <flux:input wire:model="phone" :label="__('Phone')" type="tel" placeholder="+9665XXXXXXXX" required />

            <flux:input wire:model="email" :label="__('Email')" type="email" />

            <flux:input wire:model="birthdate" :label="__('Birthdate')" type="date" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="skin_type" :label="__('Skin type')" type="text" />
                <flux:input wire:model="hair_type" :label="__('Hair type')" type="text" />
            </div>

            <flux:input wire:model="tagsInput" :label="__('Tags')" type="text" :description="__('Comma-separated, e.g. VIP, حساسية')" />

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
