@php($accent = $appointment->services->first()?->color ?: '#a1a1aa')

<button
    type="button"
    wire:click="openReschedule({{ $appointment->id }})"
    class="block w-full rounded-lg border border-zinc-200 bg-white p-2 text-start hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
    style="border-inline-start-width: 4px; border-inline-start-color: {{ $accent }};"
>
    <div class="flex items-center justify-between gap-2">
        <flux:text class="text-sm font-medium">
            {{ $appointment->datetime_start->format('H:i') }}–{{ $appointment->datetime_end->format('H:i') }}
        </flux:text>
        <flux:badge :color="$appointment->status->color()" size="sm">
            {{ __($appointment->status->label()) }}
        </flux:badge>
    </div>
    <flux:text class="mt-1 truncate text-sm">{{ $appointment->client?->name }}</flux:text>
    <flux:text class="truncate text-xs">{{ $appointment->services->pluck('name')->join('، ') }}</flux:text>
</button>
