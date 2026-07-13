<section class="w-full">
    <flux:card class="space-y-6 text-center">
        <div class="flex flex-col items-center gap-3">
            <div class="flex size-14 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/40">
                <flux:icon.check-circle class="size-8 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <flux:heading size="xl">{{ __('Booking confirmed!') }}</flux:heading>
                <flux:subheading>{{ __('We look forward to seeing you.') }}</flux:subheading>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div class="space-y-3 text-start text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Reference') }}</span>
                <span class="font-medium">#{{ $appointment->id }}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Services') }}</span>
                <span class="text-end font-medium">{{ $appointment->services->pluck('name')->join('، ') }}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Staff') }}</span>
                <span class="font-medium">{{ $appointment->staff?->name }}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('When') }}</span>
                <span class="font-medium">
                    {{ $appointment->datetime_start->format('Y-m-d') }} ·
                    {{ $appointment->datetime_start->format('H:i') }}–{{ $appointment->datetime_end->format('H:i') }}
                </span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</span>
                <flux:badge :color="$appointment->status->color()" size="sm">{{ __($appointment->status->label()) }}</flux:badge>
            </div>
            <flux:separator variant="subtle" />
            <div class="flex justify-between gap-4 text-base">
                <span class="font-semibold">{{ __('Total') }}</span>
                <span class="font-semibold">{{ $appointment->price_snapshot }} {{ $setting->currency->value }}</span>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <flux:button href="{{ $icsUrl }}" icon="calendar-days" variant="primary">
                {{ __('Add to calendar') }}
            </flux:button>

            @if ($whatsappUrl)
                <flux:button href="{{ $whatsappUrl }}" icon="chat-bubble-left-right" target="_blank">
                    {{ __('Message the salon on WhatsApp') }}
                </flux:button>
            @endif

            <flux:button href="{{ route('my-booking') }}" variant="ghost" wire:navigate>
                {{ __('View or cancel my booking') }}
            </flux:button>
        </div>
    </flux:card>
</section>
