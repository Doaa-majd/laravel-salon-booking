<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Appointments') }}</flux:heading>
                <flux:subheading size="lg">{{ __('Book, filter, and manage salon appointments') }}</flux:subheading>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New appointment') }}</flux:button>
        </div>
        <flux:separator variant="subtle" class="mt-6" />
    </div>

    {{-- Filters --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <flux:select wire:model.live="statusFilter" :label="__('Status')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($statuses as $status)
                <flux:select.option value="{{ $status->value }}">{{ __($status->label()) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="staffFilter" :label="__('Staff')">
            <flux:select.option value="">{{ __('All staff') }}</flux:select.option>
            @foreach ($allStaff as $member)
                <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live="dateFrom" type="date" :label="__('From')" />
        <flux:input wire:model.live="dateTo" type="date" :label="__('To')" />

        <flux:input
            wire:model.live.debounce.300ms="phoneSearch"
            icon="magnifying-glass"
            :label="__('Client phone')"
            :placeholder="__('+9665...')"
        />
    </div>

    @if ($appointments->isEmpty())
        <flux:callout icon="calendar">
            <flux:callout.heading>{{ __('No appointments found') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Adjust the filters or book a new appointment.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Staff') }}</flux:table.column>
                <flux:table.column>{{ __('Services') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($appointments as $appointment)
                    <flux:table.row :key="'appt-'.$appointment->id">
                        <flux:table.cell variant="strong">
                            {{ $appointment->datetime_start->format('Y-m-d') }}
                            <flux:text class="mt-0.5">
                                {{ $appointment->datetime_start->format('H:i') }}–{{ $appointment->datetime_end->format('H:i') }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $appointment->client?->name }}
                            <flux:text class="mt-0.5">{{ $appointment->client?->phone }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>{{ $appointment->staff?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $appointment->services->pluck('name')->join('، ') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$appointment->status->color()" size="sm">
                                {{ __($appointment->status->label()) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $appointment->price_snapshot }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-2">
                                @if (! $appointment->status->isTerminal())
                                    <flux:dropdown>
                                        <flux:button size="sm" variant="ghost" icon="arrow-path" icon:trailing="chevron-down">
                                            {{ __('Status') }}
                                        </flux:button>
                                        <flux:menu>
                                            @foreach ($appointment->status->allowedTransitions() as $next)
                                                <flux:menu.item wire:click="changeStatus({{ $appointment->id }}, '{{ $next->value }}')">
                                                    {{ __($next->label()) }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif

                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="chat-bubble-left-right"
                                    wire:click="sendReminder({{ $appointment->id }})"
                                >
                                    {{ __('Remind') }}
                                </flux:button>

                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $appointment->id }})">
                                    {{ __('Notes') }}
                                </flux:button>

                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="delete({{ $appointment->id }})"
                                    wire:confirm="{{ __('Delete this appointment?') }}"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    {{-- Booking modal --}}
    <flux:modal wire:model.self="showForm" class="max-w-xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New appointment') }}</flux:heading>
                <flux:subheading>{{ __('The time is re-checked against availability on save.') }}</flux:subheading>
            </div>

            <flux:select wire:model="form_client_id" :label="__('Client')" :placeholder="__('Select a client')">
                @foreach ($clients as $client)
                    <flux:select.option value="{{ $client->id }}">{{ $client->name }} — {{ $client->phone }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:checkbox.group wire:model.live="form_service_ids" :label="__('Services')">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($services as $service)
                        <flux:checkbox
                            value="{{ $service->id }}"
                            :label="$service->name.' · '.$service->duration_minutes.' '.__('min')"
                        />
                    @endforeach
                </div>
            </flux:checkbox.group>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="form_staff_id" :label="__('Staff')">
                    <flux:select.option value="">{{ __('Any available') }}</flux:select.option>
                    @foreach ($staffOptions as $member)
                        <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model.live="form_date" type="date" :label="__('Date')" />
            </div>

            <div>
                <flux:label>{{ __('Available times') }}</flux:label>
                <div class="mt-2 flex flex-wrap gap-2">
                    @forelse ($slots as $slot)
                        <flux:button
                            size="sm"
                            type="button"
                            :variant="$form_time === $slot['time'] ? 'primary' : 'outline'"
                            wire:click="$set('form_time', '{{ $slot['time'] }}')"
                        >
                            {{ $slot['time'] }}
                        </flux:button>
                    @empty
                        <flux:text>{{ __('Pick services and a date to see available times.') }}</flux:text>
                    @endforelse
                </div>
                <flux:error name="form_time" />
                <flux:error name="form_date" />
                <flux:error name="form_service_ids" />
                <flux:error name="form_client_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="form_status" :label="__('Status')">
                    <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
                    <flux:select.option value="confirmed">{{ __('Confirmed') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:textarea wire:model="form_notes" :label="__('Notes')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Book') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- WhatsApp reminder modal --}}
    <flux:modal wire:model.self="showReminder" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Send WhatsApp reminder') }}</flux:heading>
                <flux:subheading>{{ __('A reminder has been logged. Open WhatsApp to send the pre-filled message.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Close') }}</flux:button>
                </flux:modal.close>
                @if ($reminderUrl)
                    <flux:button
                        as="a"
                        href="{{ $reminderUrl }}"
                        target="_blank"
                        variant="primary"
                        icon="chat-bubble-left-right"
                    >
                        {{ __('Open WhatsApp') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>

    {{-- Notes editor modal --}}
    <flux:modal wire:model.self="showEdit" class="max-w-lg">
        <form wire:submit="updateNotes" class="space-y-6">
            <flux:heading size="lg">{{ __('Edit notes') }}</flux:heading>

            <flux:textarea wire:model="edit_notes" :label="__('Notes')" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
