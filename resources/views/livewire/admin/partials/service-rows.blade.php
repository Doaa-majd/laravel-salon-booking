<flux:table>
    <flux:table.columns>
        <flux:table.column>{{ __('Service') }}</flux:table.column>
        <flux:table.column>{{ __('Duration') }}</flux:table.column>
        <flux:table.column>{{ __('Price') }}</flux:table.column>
        <flux:table.column>{{ __('Status') }}</flux:table.column>
        <flux:table.column />
    </flux:table.columns>

    <flux:table.rows>
        @foreach ($rows as $service)
            <flux:table.row :key="'service-'.$service->id">
                <flux:table.cell variant="strong">
                    <div class="flex items-center gap-2">
                        @if ($service->color)
                            <span class="inline-block size-3 rounded-full" style="background-color: {{ $service->color }}"></span>
                        @endif
                        {{ $service->name }}
                    </div>
                </flux:table.cell>
                <flux:table.cell>{{ $service->duration_minutes }} {{ __('min') }}</flux:table.cell>
                <flux:table.cell>{{ $service->price }}</flux:table.cell>
                <flux:table.cell>
                    @if ($service->is_active)
                        <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                    @endif
                </flux:table.cell>
                <flux:table.cell align="end">
                    <div class="flex items-center justify-end gap-2">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $service->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="trash"
                            wire:click="delete({{ $service->id }})"
                            wire:confirm="{{ __('Delete this service?') }}"
                        />
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>
