<section class="w-full space-y-6">
    <flux:card class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Find my booking') }}</flux:heading>
            <flux:subheading>{{ __('Enter the phone number you booked with.') }}</flux:subheading>
        </div>

        <form wire:submit="lookup" class="space-y-4">
            <flux:input
                wire:model="phone"
                :label="__('Phone number')"
                :placeholder="__('+9665...')"
                inputmode="tel"
                dir="ltr"
            />

            <flux:button type="submit" variant="primary" icon="magnifying-glass" class="w-full">
                {{ __('Search') }}
            </flux:button>
        </form>
    </flux:card>

    @if ($searched)
        @if ($appointments->isEmpty())
            <flux:callout icon="calendar">
                <flux:callout.heading>{{ __('No upcoming bookings') }}</flux:callout.heading>
                <flux:callout.text>{{ __('We could not find any upcoming appointments for that number.') }}</flux:callout.text>
            </flux:callout>
        @else
            <div class="space-y-3">
                @foreach ($appointments as $appointment)
                    <flux:card wire:key="appt-{{ $appointment->id }}" class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <flux:heading size="sm">
                                    {{ $appointment->datetime_start->format('Y-m-d') }} ·
                                    {{ $appointment->datetime_start->format('H:i') }}
                                </flux:heading>
                                <flux:text class="mt-0.5">{{ $appointment->services->pluck('name')->join('، ') }}</flux:text>
                                <flux:text class="mt-0.5">{{ $appointment->staff?->name }}</flux:text>
                            </div>
                            <flux:badge :color="$appointment->status->color()" size="sm">
                                {{ __($appointment->status->label()) }}
                            </flux:badge>
                        </div>

                        <flux:button
                            size="sm"
                            variant="danger"
                            icon="x-mark"
                            wire:click="cancel({{ $appointment->id }})"
                            wire:confirm="{{ __('Cancel this booking?') }}"
                        >
                            {{ __('Cancel booking') }}
                        </flux:button>
                    </flux:card>
                @endforeach

                <flux:text class="text-center text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Bookings can be cancelled online up to :hours hours before they start.', ['hours' => $leadHours]) }}
                </flux:text>
            </div>
        @endif
    @endif
</section>
