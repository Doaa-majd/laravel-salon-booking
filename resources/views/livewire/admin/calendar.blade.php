<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Calendar') }}</flux:heading>
                <flux:subheading size="lg">{{ $rangeLabel }}</flux:subheading>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button.group>
                    <flux:button size="sm" :variant="$viewMode === 'day' ? 'primary' : 'outline'" wire:click="setView('day')">
                        {{ __('Day') }}
                    </flux:button>
                    <flux:button size="sm" :variant="$viewMode === 'week' ? 'primary' : 'outline'" wire:click="setView('week')">
                        {{ __('Week') }}
                    </flux:button>
                </flux:button.group>

                <flux:button.group>
                    <flux:button size="sm" variant="outline" icon="chevron-right" wire:click="previous" />
                    <flux:button size="sm" variant="outline" wire:click="today">{{ __('Today') }}</flux:button>
                    <flux:button size="sm" variant="outline" icon="chevron-left" wire:click="next" />
                </flux:button.group>

                <flux:button
                    size="sm"
                    variant="primary"
                    icon="plus"
                    wire:click="createAt(null, '{{ $days->first()->format('Y-m-d') }}')"
                >
                    {{ __('New appointment') }}
                </flux:button>
            </div>
        </div>
        <flux:separator variant="subtle" class="mt-6" />
    </div>

    @php($weekdays = [__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')])

    @if ($viewMode === 'day')
        {{-- Day view: one column per visible staff member. --}}
        @if ($columnStaff->isEmpty())
            <flux:callout icon="users">
                <flux:callout.heading>{{ __('No staff to display') }}</flux:callout.heading>
            </flux:callout>
        @else
            <div class="grid gap-4" style="grid-template-columns: repeat({{ min($columnStaff->count(), 4) }}, minmax(0, 1fr));">
                @foreach ($columnStaff as $member)
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center justify-between border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                            <flux:heading size="sm">{{ $member->name }}</flux:heading>
                            <flux:button size="xs" variant="ghost" icon="plus" wire:click="createAt({{ $member->id }}, '{{ $days->first()->format('Y-m-d') }}')" />
                        </div>
                        <div class="space-y-2 p-3">
                            @forelse ($appointments->where('staff_id', $member->id) as $appointment)
                                @include('livewire.admin.partials.calendar-block', ['appointment' => $appointment])
                            @empty
                                <flux:text class="text-sm">{{ __('No appointments') }}</flux:text>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- Week view: one column per day. --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-7">
            @foreach ($days as $day)
                @php($key = $day->format('Y-m-d'))
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                        <div>
                            <flux:heading size="sm">{{ $weekdays[$day->dayOfWeek] }}</flux:heading>
                            <flux:text class="text-xs">{{ $day->format('m-d') }}</flux:text>
                        </div>
                        <flux:button size="xs" variant="ghost" icon="plus" wire:click="createAt(null, '{{ $key }}')" />
                    </div>
                    <div class="space-y-2 p-3">
                        @forelse ($byDate->get($key, collect()) as $appointment)
                            @include('livewire.admin.partials.calendar-block', ['appointment' => $appointment])
                        @empty
                            <flux:text class="text-sm">{{ __('—') }}</flux:text>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Create modal --}}
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
                    @forelse ($createSlots as $slot)
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

            <flux:select wire:model="form_status" :label="__('Status')" class="sm:max-w-xs">
                <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
                <flux:select.option value="confirmed">{{ __('Confirmed') }}</flux:select.option>
            </flux:select>

            <flux:textarea wire:model="form_notes" :label="__('Notes')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Book') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Reschedule modal --}}
    <flux:modal wire:model.self="showReschedule" class="max-w-lg">
        <form wire:submit="reschedule" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reschedule appointment') }}</flux:heading>
                <flux:subheading>{{ __('The new time is re-checked against availability.') }}</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="reschedule_staff_id" :label="__('Staff')">
                    @foreach ($columnStaff as $member)
                        <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model.live="reschedule_date" type="date" :label="__('Date')" />
            </div>

            <div>
                <flux:label>{{ __('Available times') }}</flux:label>
                <div class="mt-2 flex flex-wrap gap-2">
                    @forelse ($rescheduleSlots as $slot)
                        <flux:button
                            size="sm"
                            type="button"
                            :variant="$reschedule_time === $slot['time'] ? 'primary' : 'outline'"
                            wire:click="$set('reschedule_time', '{{ $slot['time'] }}')"
                        >
                            {{ $slot['time'] }}
                        </flux:button>
                    @empty
                        <flux:text>{{ __('No available times for this day.') }}</flux:text>
                    @endforelse
                </div>
                <flux:error name="reschedule_time" />
                <flux:error name="reschedule_date" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Reschedule') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
