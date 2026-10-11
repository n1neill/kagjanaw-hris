<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Kagjanaw HRIS') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans text-gray-900 antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:shadow">
            {{ __('Skip to main content') }}
        </a>

        <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="min-h-full">
            {{-- Mobile sidebar drawer --}}
            <div x-cloak x-show="sidebarOpen" class="relative z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Navigation') }}">
                <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-gray-900/60" @click="sidebarOpen = false"></div>

                <div x-show="sidebarOpen"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="-translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="-translate-x-full"
                     class="fixed inset-y-0 left-0 flex w-72 max-w-[85%] flex-col bg-brand-900">
                    <div class="flex h-16 items-center justify-between px-4">
                        @include('layouts.partials.brand')
                        <button type="button" class="rounded-md p-2 text-brand-100 hover:bg-brand-800 hover:text-white" @click="sidebarOpen = false">
                            <span class="sr-only">{{ __('Close navigation') }}</span>
                            <x-icon name="x" class="size-6" />
                        </button>
                    </div>
                    @include('layouts.partials.sidebar-nav')
                </div>
            </div>

            {{-- Desktop sidebar --}}
            <aside class="hidden lg:fixed lg:inset-y-0 lg:z-30 lg:flex lg:w-64 lg:flex-col lg:bg-brand-900">
                <div class="flex h-16 items-center px-4">
                    @include('layouts.partials.brand')
                </div>
                @include('layouts.partials.sidebar-nav')
            </aside>

            <div class="lg:pl-64">
                {{-- Top bar --}}
                <div class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                    <button type="button" class="-ml-2 rounded-md p-2 text-gray-600 hover:bg-gray-100 lg:hidden" @click="sidebarOpen = true">
                        <span class="sr-only">{{ __('Open navigation') }}</span>
                        <x-icon name="menu" class="size-6" />
                    </button>

                    <div class="min-w-0 flex-1">
                        @isset($header)
                            <h1 class="truncate text-lg font-semibold text-gray-900">{{ $header }}</h1>
                        @endisset
                    </div>

                    {{-- Notifications (wired to Laravel database notifications in Phase 6) --}}
                    <x-dropdown align="right" width="w-72">
                        <x-slot name="trigger">
                            <button type="button" class="relative rounded-full p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                <span class="sr-only">{{ __('View notifications') }}</span>
                                <x-icon name="bell" class="size-6" />
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <p class="px-4 py-3 text-sm text-gray-500">{{ __('You have no notifications.') }}</p>
                        </x-slot>
                    </x-dropdown>

                    <x-dropdown align="right" width="w-56">
                        <x-slot name="trigger">
                            <button type="button" class="flex items-center gap-2 rounded-md p-1.5 text-sm hover:bg-gray-100">
                                <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800" aria-hidden="true">
                                    {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                                </span>
                                <span class="hidden text-left sm:block">
                                    <span class="block max-w-40 truncate font-medium text-gray-900">{{ Auth::user()->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ Auth::user()->primaryRole()?->label() }}</span>
                                </span>
                                <x-icon name="chevron-down" class="hidden size-4 text-gray-400 sm:block" />
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="border-b border-gray-100 px-4 py-2 sm:hidden">
                                <p class="truncate text-sm font-medium text-gray-900">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-gray-500">{{ Auth::user()->primaryRole()?->label() }}</p>
                            </div>
                            <x-dropdown-link :href="route('profile.edit')">{{ __('My Account') }}</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition hover:bg-gray-100 focus:bg-gray-100 focus:outline-hidden">
                                    {{ __('Log Out') }}
                                </button>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>

                <main id="main-content" class="px-4 py-6 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-7xl space-y-6">
                        @if (session('status'))
                            <div x-data="{ show: true }" x-show="show" role="status" class="flex items-start justify-between gap-3 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                                <span>{{ session('status') }}</span>
                                <button type="button" class="text-green-700 hover:text-green-900" @click="show = false">
                                    <span class="sr-only">{{ __('Dismiss') }}</span>
                                    <x-icon name="x" class="size-4" />
                                </button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                                {{ session('error') }}
                            </div>
                        @endif

                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
