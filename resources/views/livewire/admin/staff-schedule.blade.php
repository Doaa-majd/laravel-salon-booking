<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Schedule') }} — {{ $staff->name }}</flux:heading>
                <flux:subheading size="lg">{{ __('Set weekly working hours and record time off') }}</flux:subheading>
            </div>

            <flux:button variant="ghost" icon="arrow-uturn-left" :href="route('admin.staff.index')" wire:navigate>
                {{ __('Back to staff') }}
            </flux:button>
        </div>
        <flux:separator variant="subtle" class="mt-6" />
    </div>

    {{-- Weekly schedule --}}
    <form wire:submit="saveSchedule" class="mb-10">
        <flux:heading size="lg" class="mb-4">{{ __('Weekly working hours') }}</flux:heading>

        <div class="space-y-3">
            @foreach ($weekdayNames as $weekday => $label)
                <div class="grid grid-cols-1 items-center gap-3 rounded-lg border border-zinc-200 p-3 sm:grid-cols-[8rem_1fr_1fr_auto] dark:border-zinc-700">
                    <flux:text class="font-medium">{{ $label }}</flux:text>

                    <flux:input
                        type="time"
                        wire:model="schedule.{{ $weekday }}.start_time"
                        :label="__('Start')"
                        :disabled="$schedule[$weekday]['is_off']"
                    />

                    <flux:input
                        type="time"
                        wire:model="schedule.{{ $weekday }}.end_time"
                        :label="__('End')"
                        :disabled="$schedule[$weekday]['is_off']"
                    />

                    <div class="sm:self-end sm:pb-2">
                        <flux:switch wire:model.live="schedule.{{ $weekday }}.is_off" :label="__('Day off')" />
                    </div>
                </div>
            @endforeach
        </div>

        @error('schedule.*.end_time')
            <flux:text class="mt-2" variant="danger">{{ $message }}</flux:text>
        @enderror

        <div class="mt-4 flex justify-end">
            <flux:button variant="primary" type="submit">{{ __('Save schedule') }}</flux:button>
        </div>
    </form>

    <flux:separator variant="subtle" class="my-8" />

    {{-- Time off --}}
    <div>
        <flux:heading size="lg" class="mb-4">{{ __('Time off') }}</flux:heading>

        <form wire:submit="addTimeOff" class="mb-6 grid grid-cols-1 items-end gap-3 sm:grid-cols-5">
            <flux:input type="date" wire:model="timeOffDate" :label="__('Date')" required />
            <flux:input type="time" wire:model="timeOffStart" :label="__('From (optional)')" />
            <flux:input type="time" wire:model="timeOffEnd" :label="__('To (optional)')" />
            <flux:input type="text" wire:model="timeOffReason" :label="__('Reason')" maxlength="150" />
            <flux:button variant="primary" type="submit" icon="plus">{{ __('Add') }}</flux:button>
        </form>

        @if ($timeOff->isEmpty())
            <flux:callout icon="calendar-days">
                <flux:callout.heading>{{ __('No time off recorded') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Add a full-day or partial-day leave above.') }}</flux:callout.text>
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Window') }}</flux:table.column>
                    <flux:table.column>{{ __('Reason') }}</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($timeOff as $entry)
                        <flux:table.row :key="'timeoff-'.$entry->id">
                            <flux:table.cell variant="strong">{{ $entry->date->format('Y-m-d') }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($entry->start_time && $entry->end_time)
                                    {{ substr($entry->start_time, 0, 5) }} – {{ substr($entry->end_time, 0, 5) }}
                                @else
                                    <flux:badge color="amber" size="sm">{{ __('Full day') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $entry->reason ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="removeTimeOff({{ $entry->id }})"
                                    wire:confirm="{{ __('Remove this time off?') }}"
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</section>
