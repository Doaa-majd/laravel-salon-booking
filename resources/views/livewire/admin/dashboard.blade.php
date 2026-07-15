<section class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
                <flux:subheading size="lg">{{ __("Today's overview") }} — {{ now()->format('Y-m-d') }}</flux:subheading>
            </div>

            <flux:button variant="primary" icon="calendar-days" :href="route('admin.calendar')" wire:navigate>
                {{ __('Calendar') }}
            </flux:button>
        </div>
        <flux:separator variant="subtle" class="mt-6" />
    </div>

    {{-- KPI cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __("Today's appointments") }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $todayCount }}</flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Expected revenue') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ number_format($revenue, 2) }} {{ $currency }}</flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('No-shows today') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $noShows }}</flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Next up') }}</flux:text>
            @if ($nextUp)
                <flux:heading size="xl" class="mt-2">{{ $nextUp->datetime_start->format('H:i') }}</flux:heading>
                <flux:text class="mt-0.5">{{ $nextUp->client?->name }} · {{ $nextUp->staff?->name }}</flux:text>
            @else
                <flux:heading size="xl" class="mt-2">—</flux:heading>
                <flux:text class="mt-0.5">{{ __('Nothing left today') }}</flux:text>
            @endif
        </div>
    </div>

    {{-- Today's timeline --}}
    <flux:heading size="lg" class="mt-8 mb-3">{{ __("Today's schedule") }}</flux:heading>

    @if ($appointments->isEmpty())
        <flux:callout icon="calendar">
            <flux:callout.heading>{{ __('No appointments today') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Enjoy the quiet, or book a new appointment.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Staff') }}</flux:table.column>
                <flux:table.column>{{ __('Services') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($appointments as $appointment)
                    <flux:table.row :key="'today-'.$appointment->id">
                        <flux:table.cell variant="strong">
                            {{ $appointment->datetime_start->format('H:i') }}–{{ $appointment->datetime_end->format('H:i') }}
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
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
