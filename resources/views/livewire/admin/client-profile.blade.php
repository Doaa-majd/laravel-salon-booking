@php($currency = \App\Models\Setting::current()->currency->value)

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('admin.clients.index')" wire:navigate class="mb-4">
            {{ __('Back to clients') }}
        </flux:button>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $client->name }}</flux:heading>
                <flux:subheading size="lg">{{ $client->phone }}</flux:subheading>
            </div>

            <div class="flex flex-wrap gap-1">
                @foreach ($client->tags ?? [] as $tag)
                    <flux:badge color="blue">{{ $tag }}</flux:badge>
                @endforeach
            </div>
        </div>
        <flux:separator variant="subtle" class="mt-6" />
    </div>

    {{-- Aggregates --}}
    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Total spend') }}</flux:text>
            <flux:heading size="lg">{{ number_format((float) $totalSpend, 2) }} {{ $currency }}</flux:heading>
            <flux:text class="text-xs">{{ __('Completed appointments only') }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Visits') }}</flux:text>
            <flux:heading size="lg">{{ $appointments->count() }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Last visit') }}</flux:text>
            <flux:heading size="lg">
                {{ $appointments->first()?->datetime_start?->format('Y-m-d') ?? '—' }}
            </flux:heading>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-3">
        {{-- Cosmetic profile --}}
        <div class="space-y-4 lg:col-span-1">
            <flux:heading size="lg">{{ __('Cosmetic profile') }}</flux:heading>

            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</dt>
                    <dd>{{ $client->email ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Birthdate') }}</dt>
                    <dd>{{ $client->birthdate?->format('Y-m-d') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Skin type') }}</dt>
                    <dd>{{ $client->skin_type ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Hair type') }}</dt>
                    <dd>{{ $client->hair_type ?? '—' }}</dd>
                </div>
            </dl>

            @if ($client->notes)
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Notes') }}</flux:text>
                    <flux:text class="mt-1">{{ $client->notes }}</flux:text>
                </div>
            @endif
        </div>

        {{-- Appointment history --}}
        <div class="space-y-4 lg:col-span-2">
            <flux:heading size="lg">{{ __('Appointment history') }}</flux:heading>

            @if ($appointments->isEmpty())
                <flux:callout icon="calendar">
                    <flux:callout.text>{{ __('No appointments yet.') }}</flux:callout.text>
                </flux:callout>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Staff') }}</flux:table.column>
                        <flux:table.column>{{ __('Services') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Total') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($appointments as $appointment)
                            <flux:table.row :key="'appt-'.$appointment->id">
                                <flux:table.cell>{{ $appointment->datetime_start->format('Y-m-d H:i') }}</flux:table.cell>
                                <flux:table.cell>{{ $appointment->staff?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ $appointment->appointmentServices->map(fn ($s) => $s->service?->name)->filter()->implode('، ') ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="match ($appointment->status->value) {
                                        'completed' => 'green',
                                        'confirmed' => 'blue',
                                        'pending' => 'amber',
                                        'cancelled' => 'zinc',
                                        'no_show' => 'red',
                                        default => 'zinc',
                                    }">{{ $appointment->status->value }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ number_format((float) $appointment->price_snapshot, 2) }} {{ $currency }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </div>

    <flux:separator variant="subtle" class="my-8" />

    {{-- Before/after photo gallery --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Before / after gallery') }}</flux:heading>
        </div>

        <form wire:submit="addPhoto" class="grid gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input type="file" wire:model="photo" :label="__('Photo')" accept="image/*" required />

            <flux:select wire:model="photoType" :label="__('Type')">
                <flux:select.option value="before">{{ __('Before') }}</flux:select.option>
                <flux:select.option value="after">{{ __('After') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model="photoAppointmentId" :label="__('Appointment (optional)')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($appointments as $appointment)
                    <flux:select.option value="{{ $appointment->id }}">
                        {{ $appointment->datetime_start->format('Y-m-d') }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="photoTakenAt" :label="__('Taken at')" type="datetime-local" required />

            <div class="flex items-end">
                <flux:button variant="primary" type="submit" icon="plus">{{ __('Add photo') }}</flux:button>
            </div>

            <flux:input wire:model="photoNote" :label="__('Note')" type="text" class="sm:col-span-2 lg:col-span-5" />
        </form>

        @if ($photos->isEmpty())
            <flux:callout icon="photo">
                <flux:callout.text>{{ __('No photos yet.') }}</flux:callout.text>
            </flux:callout>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($photos as $photo)
                    <div class="group relative overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" wire:key="photo-{{ $photo->id }}">
                        <img
                            src="{{ Storage::disk('public')->url($photo->image_path) }}"
                            alt="{{ $photo->note ?? $photo->type->value }}"
                            class="aspect-square w-full object-cover"
                        />
                        <div class="absolute inset-x-0 top-0 flex items-center justify-between p-2">
                            <flux:badge size="sm" :color="$photo->type->value === 'before' ? 'amber' : 'green'">
                                {{ $photo->type->value === 'before' ? __('Before') : __('After') }}
                            </flux:badge>
                            <flux:button
                                size="xs"
                                variant="danger"
                                icon="trash"
                                wire:click="removePhoto({{ $photo->id }})"
                                wire:confirm="{{ __('Remove this photo?') }}"
                            />
                        </div>
                        <div class="p-2 text-xs">
                            <div class="text-zinc-500 dark:text-zinc-400">{{ $photo->taken_at->format('Y-m-d') }}</div>
                            @if ($photo->note)
                                <div class="truncate">{{ $photo->note }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
