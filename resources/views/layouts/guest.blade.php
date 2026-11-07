<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-brand-950" data-theme="{{ Auth::user()?->theme->value ?? 'system' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Cagdianao HRIS') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-surface font-sans text-ink antialiased">
        <div class="relative isolate flex min-h-full flex-col overflow-hidden px-6 py-8 sm:px-12">
            {{-- Concentric rings: a quiet nod to the municipal seal --}}
            <div class="pointer-events-none absolute -right-48 -top-48 -z-10 hidden size-[720px] rounded-full border border-brand-500/15 sm:block" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -right-32 -top-32 -z-10 hidden size-[560px] rounded-full border border-brand-500/20 sm:block" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -right-16 -top-16 -z-10 hidden size-[400px] rounded-full bg-brand-500/10 sm:block" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-40 -left-40 -z-10 size-[420px] rounded-full border border-amber-400/30" aria-hidden="true"></div>

            <header class="flex items-center gap-3">
                <x-application-logo class="size-12" />
                <span class="text-xl font-semibold tracking-tight">Cagdianao HRIS</span>
            </header>

            <main class="mx-auto my-auto w-full max-w-md py-12">
                <div class="rounded-2xl border border-line bg-surface p-7 shadow-2xl shadow-brand-900/15 ring-1 ring-black/5 sm:p-10">
                    <span class="mb-6 block h-1 w-10 rounded-full bg-amber-400"></span>
                    {{ $slot }}
                </div>
            </main>

            <footer class="flex items-center justify-between text-xs text-ink-muted">
                <span>{{ __('Municipality of Cagdianao, Dinagat Islands') }}</span>
                <span class="hidden items-center gap-1.5 sm:flex">
                    <x-icon name="shield" class="size-4" />
                    {{ __('Authorized personnel only') }}
                </span>
            </footer>
        </div>
    </body>
</html>
