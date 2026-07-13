<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        @php($salon = \App\Models\Setting::current())

        <div class="flex min-h-svh flex-col items-center px-4 py-6 sm:py-10">
            <header class="mb-6 flex w-full max-w-lg items-center justify-between gap-4">
                <a href="{{ route('book') }}" class="flex items-center gap-2 font-medium" wire:navigate>
                    <span class="flex size-9 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-8 fill-current text-black dark:text-white" />
                    </span>
                    <span class="text-lg font-semibold">{{ $salon->salon_name }}</span>
                </a>

                <flux:button href="{{ route('my-booking') }}" size="sm" variant="ghost" icon="calendar" wire:navigate>
                    {{ __('My booking') }}
                </flux:button>
            </header>

            <main class="w-full max-w-lg">
                {{ $slot }}
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
