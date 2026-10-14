<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-canvas" data-theme="{{ Auth::user()?->theme->value ?? 'system' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Kagjanaw HRIS') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans text-ink antialiased">
        <div class="flex min-h-full">
            {{-- Brand panel (large screens) --}}
            <aside class="relative hidden w-1/2 max-w-xl flex-col justify-between overflow-hidden bg-linear-to-br from-brand-700 via-brand-800 to-brand-950 p-10 text-white lg:flex xl:p-12">
                <svg class="pointer-events-none absolute inset-x-0 bottom-0 h-64 w-full text-white/10" viewBox="0 0 600 240" preserveAspectRatio="none" fill="none" aria-hidden="true">
                    <path d="M0 120c75-40 150-40 225 0s150 40 225 0 112-40 150-20" stroke="currentColor" stroke-width="2" />
                    <path d="M0 160c75-40 150-40 225 0s150 40 225 0 112-40 150-20" stroke="currentColor" stroke-width="2" />
                    <path d="M0 200c75-40 150-40 225 0s150 40 225 0 112-40 150-20" stroke="currentColor" stroke-width="2" />
                </svg>
                <div class="pointer-events-none absolute -right-24 -top-24 size-72 rounded-full bg-brand-400/20 blur-3xl" aria-hidden="true"></div>

                <div class="relative flex items-center gap-3">
                    <x-application-logo class="size-11 text-brand-500" />
                    <div class="leading-tight">
                        <p class="text-lg font-semibold">Kagjanaw HRIS</p>
                        <p class="text-sm text-brand-100">{{ __('Municipality of Cagdianao') }}</p>
                    </div>
                </div>

                <div class="relative max-w-md">
                    <p class="text-3xl font-semibold leading-tight tracking-tight xl:text-4xl">{{ __('Human Resource Information System') }}</p>
                    <p class="mt-4 text-base text-brand-100">{{ __('Attendance, leave and payroll for the employees of the Municipality of Cagdianao, in one place.') }}</p>
                    <ul class="mt-8 space-y-3 text-sm text-brand-50">
                        @foreach ([__('Daily time records from the biometric devices'), __('Leave applications, endorsements and credits'), __('Payroll, payslips and remittance reports')] as $feature)
                            <li class="flex items-center gap-3">
                                <span class="flex size-6 items-center justify-center rounded-full bg-white/15" aria-hidden="true">
                                    <x-icon name="check" class="size-3.5" />
                                </span>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="relative text-xs text-brand-200">{{ __('Province of Dinagat Islands') }}</p>
            </aside>

            {{-- Form --}}
            <div class="flex flex-1 flex-col items-center justify-center px-4 py-10 sm:px-6">
                <div class="mb-8 flex flex-col items-center text-center lg:hidden">
                    <x-application-logo class="size-14 text-brand-600" />
                    <p class="mt-3 text-xl font-semibold text-ink">Kagjanaw HRIS</p>
                    <p class="mt-1 text-sm text-ink-muted">{{ __('Municipality of Cagdianao, Province of Dinagat Islands') }}</p>
                </div>

                <main class="w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-sm sm:p-8">
                    {{ $slot }}
                </main>

                <p class="mt-6 flex items-center gap-1.5 text-xs text-ink-muted">
                    <x-icon name="shield" class="size-4" />
                    {{ __('For authorized personnel only.') }}
                </p>
            </div>
        </div>
    </body>
</html>
