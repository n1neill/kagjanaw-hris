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
    <body class="h-full font-sans text-ink antialiased">
        <div class="relative isolate flex min-h-full flex-col items-center justify-center overflow-hidden px-4 py-10 sm:px-6">
            {{-- Municipal hall photo with a dark emerald wash so the card stays readable --}}
            <img src="{{ asset('images/login-bg.png') }}" alt="" class="absolute inset-0 -z-20 size-full scale-105 object-cover blur-[2px]" aria-hidden="true">
            <div class="absolute inset-0 -z-10 bg-linear-to-br from-brand-950/85 via-brand-900/70 to-brand-800/60" aria-hidden="true"></div>

            <div class="mb-6 flex flex-col items-center text-center text-white">
                <x-application-logo class="size-24 shadow-lg ring-4 ring-white/30 sm:size-28" />
                <h1 class="mt-4 text-2xl font-semibold tracking-tight sm:text-3xl">Cagdianao HRIS</h1>
                <p class="mt-1 text-sm text-brand-100">{{ __('Human Resource Information System') }}</p>
                <p class="text-sm text-brand-100">{{ __('Municipality of Cagdianao, Province of Dinagat Islands') }}</p>
            </div>

            <main class="w-full max-w-md rounded-2xl border border-white/20 bg-surface/95 p-6 shadow-xl backdrop-blur sm:p-8">
                {{ $slot }}
            </main>

            <p class="mt-6 flex items-center gap-1.5 text-xs text-brand-100">
                <x-icon name="shield" class="size-4" />
                {{ __('For authorized personnel only.') }}
            </p>
        </div>
    </body>
</html>
