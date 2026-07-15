<section class="w-full">
    <div class="relative mb-6 w-full">
        <div>
            <flux:heading size="xl" level="1">{{ __('Reports') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Service demand, staff performance, and commission over a date range') }}</flux:subheading>
        </div>
        <flux:separator variant="subtle" class="mt-6" />
    </div>

    {{-- Date range --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input wire:model.live="dateFrom" type="date" :label="__('From')" />
        <flux:input wire:model.live="dateTo" type="date" :label="__('To')" />
    </div>

    {{-- Headline totals --}}
    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Completed appointments') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $totals['completed_count'] }}</flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Revenue') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ number_format($totals['revenue'], 2) }} {{ $currency }}</flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Total commission') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ number_format($totals['commission'], 2) }} {{ $currency }}</flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('No-shows') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ $totals['no_shows'] }}</flux:heading>
        </div>
    </div>

    {{-- Most-requested services --}}
    <flux:heading size="lg" class="mb-3">{{ __('Most-requested services') }}</flux:heading>

    @if ($topServices === [])
        <flux:callout icon="chart-bar" class="mb-8">
            <flux:callout.heading>{{ __('No completed services in this range') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Widen the date range to see service demand.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table class="mb-8">
            <flux:table.columns>
                <flux:table.column>{{ __('Service') }}</flux:table.column>
                <flux:table.column>{{ __('Bookings') }}</flux:table.column>
                <flux:table.column>{{ __('Revenue') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($topServices as $service)
                    <flux:table.row :key="'svc-'.$service['service_id']">
                        <flux:table.cell variant="strong">{{ $service['name'] }}</flux:table.cell>
                        <flux:table.cell>{{ $service['bookings'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($service['revenue'], 2) }} {{ $currency }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    {{-- Staff performance & commission --}}
    <flux:heading size="lg" class="mb-3">{{ __('Staff performance') }}</flux:heading>

    @if ($staffPerformance === [])
        <flux:callout icon="users">
            <flux:callout.heading>{{ __('No staff to report on') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Add staff members to see performance and commission.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Staff') }}</flux:table.column>
                <flux:table.column>{{ __('Completed') }}</flux:table.column>
                <flux:table.column>{{ __('Revenue') }}</flux:table.column>
                <flux:table.column>{{ __('Commission %') }}</flux:table.column>
                <flux:table.column>{{ __('Commission') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($staffPerformance as $member)
                    <flux:table.row :key="'staff-'.$member['staff_id']">
                        <flux:table.cell variant="strong">{{ $member['name'] }}</flux:table.cell>
                        <flux:table.cell>{{ $member['completed_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($member['revenue'], 2) }} {{ $currency }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($member['commission_percent'], 2) }}%</flux:table.cell>
                        <flux:table.cell>{{ number_format($member['commission'], 2) }} {{ $currency }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
