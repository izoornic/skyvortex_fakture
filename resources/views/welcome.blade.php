<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex min-h-svh flex-col items-center justify-center gap-8 p-6 md:p-10">
            <div class="flex max-w-md flex-col items-center gap-4 text-center">
                <x-app-logo-icon class="h-14 w-auto" />

                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">
                    {{ config('app.name') }}
                </h1>

                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Fakture i ugovori sa mesečnim obračunom, PDF sa IPS QR kodom i objedinjene
                    pošiljke po upravnicima.
                </p>
            </div>

            @auth
                <flux:button variant="primary" icon="home" :href="route('dashboard')">
                    Kontrolna tabla
                </flux:button>
            @else
                <flux:button variant="primary" icon="arrow-right" :href="route('login')">
                    Prijava
                </flux:button>

                {{-- Naloge otvara administrator; registracije nema. --}}
                <p class="text-xs text-zinc-500 dark:text-zinc-500">
                    Nalog otvara administrator.
                </p>
            @endauth

            <p class="text-xs text-zinc-400 dark:text-zinc-600">
                {{ config('global.siteFooter') }} · verzija {{ config('global.version') }}
            </p>
        </div>
        @fluxScripts
    </body>
</html>
