<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Salon settings') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage your salon profile, working hours, and reminders') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <form wire:submit="save" class="w-full max-w-2xl space-y-6">
        <flux:input wire:model="salon_name" :label="__('Salon name')" type="text" required />

        <div>
            <flux:input type="file" wire:model="logo" :label="__('Logo')" accept="image/*" />

            @if ($existing_logo)
                <flux:text class="mt-2">{{ __('Current logo:') }} {{ $existing_logo }}</flux:text>
            @endif
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="primary_color" :label="__('Primary color')" type="text" placeholder="#1E88E5" />

            <flux:select wire:model="currency" :label="__('Currency')">
                <flux:select.option value="SAR">SAR</flux:select.option>
                <flux:select.option value="AED">AED</flux:select.option>
            </flux:select>

            <flux:input wire:model="timezone" :label="__('Timezone')" type="text" required />

            <flux:select wire:model="week_start" :label="__('Week starts on')">
                <flux:select.option value="0">{{ __('Sunday') }}</flux:select.option>
                <flux:select.option value="6">{{ __('Saturday') }}</flux:select.option>
                <flux:select.option value="1">{{ __('Monday') }}</flux:select.option>
            </flux:select>

            <flux:input wire:model="phone" :label="__('Phone')" type="text" />

            <flux:input wire:model="whatsapp_number" :label="__('WhatsApp number')" type="text" />
        </div>

        <flux:input wire:model="address" :label="__('Address')" type="text" />

        <flux:textarea wire:model="reminder_template" :label="__('Reminder template')" rows="3" />

        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Working hours') }}</flux:heading>

            @php($labels = [__('Sunday'), __('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday')])

            @foreach ($working_hours as $weekday => $day)
                <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <span class="w-24 font-medium">{{ $labels[$weekday] }}</span>

                    <flux:checkbox wire:model.live="working_hours.{{ $weekday }}.closed" :label="__('Closed')" />

                    <flux:input
                        wire:model="working_hours.{{ $weekday }}.open"
                        type="time"
                        class="w-32"
                        :disabled="$day['closed']"
                    />

                    <flux:input
                        wire:model="working_hours.{{ $weekday }}.close"
                        type="time"
                        class="w-32"
                        :disabled="$day['closed']"
                    />
                </div>
            @endforeach

            <flux:error name="working_hours" />
        </div>

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </div>
    </form>
</section>
