<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Kagjanaw HRIS') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans text-gray-900 antialiased">
        <div class="flex min-h-full flex-col items-center justify-center bg-linear-to-b from-brand-900 to-brand-700 px-4 py-10">
            <div class="flex flex-col items-center text-center">
                <x-application-logo class="size-16 text-brand-500" />
                <h1 class="mt-4 text-2xl font-semibold text-white">Kagjanaw HRIS</h1>
                <p class="mt-1 text-sm text-brand-100">{{ __('Municipality of Cagdianao, Province of Dinagat Islands') }}</p>
            </div>

            <main class="mt-8 w-full max-w-md rounded-lg bg-white p-6 shadow-xl sm:p-8">
                {{ $slot }}
            </main>

            <p class="mt-6 text-xs text-brand-100">{{ __('For authorized personnel only.') }}</p>
        </div>
    </body>
</html>
