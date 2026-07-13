<section class="w-full">
    @php($steps = [1 => __('Services'), 2 => __('Staff'), 3 => __('Time'), 4 => __('Details'), 5 => __('Confirm')])

    {{-- Progress --}}
    <div class="mb-6 flex items-center justify-between gap-1">
        @foreach ($steps as $index => $label)
            <div class="flex flex-1 flex-col items-center gap-1.5">
                <div @class([
                    'flex size-7 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-accent text-accent-foreground' => $index <= $step,
                    'bg-zinc-200 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400' => $index > $step,
                ])>{{ $index }}</div>
                <span class="hidden text-xs text-zinc-500 sm:block dark:text-zinc-400">{{ $label }}</span>
            </div>
        @endforeach
    </div>

    <flux:card class="space-y-6">
        {{-- Step 1: services --}}
        @if ($step === 1)
            <div>
                <flux:heading size="lg">{{ __('Choose your services') }}</flux:heading>
                <flux:subheading>{{ __('Pick one or more services to book together.') }}</flux:subheading>
            </div>

            @if ($categories->isEmpty())
                <flux:callout icon="information-circle">
                    <flux:callout.text>{{ __('No services are available for booking right now.') }}</flux:callout.text>
                </flux:callout>
            @else
                <flux:checkbox.group wire:model.live="serviceIds">
                    <div class="space-y-5">
                        @foreach ($categories as $category)
                            <div>
                                <flux:heading size="sm" class="mb-2">{{ $category->name }}</flux:heading>
                                <div class="space-y-2">
                                    @foreach ($category->services as $service)
                                        <flux:checkbox value="{{ $service->id }}">
                                            <flux:checkbox.label>
                                                <span class="flex w-full items-center justify-between gap-3">
                                                    <span>{{ $service->name }}</span>
                                                    <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                                        {{ $service->duration_minutes }} {{ __('min') }} ·
                                                        {{ $service->price }} {{ $setting->currency->value }}
                                                    </span>
                                                </span>
                                            </flux:checkbox.label>
                                        </flux:checkbox>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:checkbox.group>
            @endif

            <flux:error name="serviceIds" />
        @endif

        {{-- Step 2: staff --}}
        @if ($step === 2)
            <div>
                <flux:heading size="lg">{{ __('Choose a staff member') }}</flux:heading>
                <flux:subheading>{{ __('Only staff who provide all selected services are shown.') }}</flux:subheading>
            </div>

            <flux:radio.group wire:model.live="staffId">
                <div class="space-y-2">
                    <flux:radio value="" :label="__('Any available staff')" />
                    @foreach ($staffOptions as $member)
                        <flux:radio value="{{ $member->id }}" :label="$member->name" />
                    @endforeach
                </div>
            </flux:radio.group>
        @endif

        {{-- Step 3: date + time --}}
        @if ($step === 3)
            <div>
                <flux:heading size="lg">{{ __('Choose a date and time') }}</flux:heading>
                <flux:subheading>{{ __('Available times update as you change the date.') }}</flux:subheading>
            </div>

            <flux:input wire:model.live="date" type="date" :label="__('Date')" :min="now($setting->timezone)->toDateString()" />
            <flux:error name="date" />

            <div>
                <flux:label>{{ __('Available times') }}</flux:label>
                <div class="mt-2 flex flex-wrap gap-2">
                    @forelse ($slots as $slot)
                        <flux:button
                            size="sm"
                            type="button"
                            :variant="$time === $slot['time'] ? 'primary' : 'outline'"
                            wire:click="selectSlot('{{ $slot['time'] }}')"
                            wire:key="slot-{{ $slot['time'] }}"
                        >
                            {{ $slot['time'] }}
                        </flux:button>
                    @empty
                        <flux:text>{{ __('No available times on this date. Try another day.') }}</flux:text>
                    @endforelse
                </div>
                <flux:error name="time" />
            </div>
        @endif

        {{-- Step 4: client details --}}
        @if ($step === 4)
            <div>
                <flux:heading size="lg">{{ __('Your details') }}</flux:heading>
                <flux:subheading>{{ __('We use your phone number to find your booking later.') }}</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Full name')" required />
            <flux:input wire:model="phone" :label="__('Phone number')" :placeholder="__('+9665...')" inputmode="tel" required />
            <flux:input wire:model="email" type="email" :label="__('Email (optional)')" />
        @endif

        {{-- Step 5: review --}}
        @if ($step === 5)
            <div>
                <flux:heading size="lg">{{ __('Confirm your booking') }}</flux:heading>
                <flux:subheading>{{ __('Please review the details before confirming.') }}</flux:subheading>
            </div>

            <div class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Services') }}</span>
                    <span class="text-end font-medium">{{ $selectedServices->pluck('name')->join('، ') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Staff') }}</span>
                    <span class="font-medium">{{ $staffId ? $staffOptions->firstWhere('id', $staffId)?->name : __('Any available') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('When') }}</span>
                    <span class="font-medium">{{ $date }} · {{ $time }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Duration') }}</span>
                    <span class="font-medium">{{ $totalDuration }} {{ __('min') }}</span>
                </div>
                <flux:separator variant="subtle" />
                <div class="flex justify-between gap-4">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</span>
                    <span class="font-medium">{{ $name }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Phone') }}</span>
                    <span class="font-medium" dir="ltr">{{ $phone }}</span>
                </div>
                <flux:separator variant="subtle" />
                <div class="flex justify-between gap-4 text-base">
                    <span class="font-semibold">{{ __('Total') }}</span>
                    <span class="font-semibold">{{ number_format($totalPrice, 2) }} {{ $setting->currency->value }}</span>
                </div>
            </div>

            {{-- Errors surfaced from the final re-validation / re-check --}}
            <flux:error name="time" />
            <flux:error name="phone" />
            <flux:error name="name" />
        @endif

        {{-- Navigation --}}
        <div class="flex items-center justify-between gap-2 pt-2">
            @if ($step > 1)
                <flux:button type="button" variant="ghost" icon="chevron-right" wire:click="back">{{ __('Back') }}</flux:button>
            @else
                <span></span>
            @endif

            @if ($step < 5)
                <flux:button type="button" variant="primary" icon:trailing="chevron-left" wire:click="next">{{ __('Next') }}</flux:button>
            @else
                <flux:button type="button" variant="primary" icon="check" wire:click="submit">{{ __('Confirm booking') }}</flux:button>
            @endif
        </div>
    </flux:card>
</section>
