<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-canvas" data-theme="{{ Auth::user()->theme->value }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Kagjanaw HRIS') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans text-ink antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:shadow">
            {{ __('Skip to main content') }}
        </a>

        <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="min-h-full">
            {{-- Mobile sidebar drawer --}}
            <div x-cloak x-show="sidebarOpen" class="relative z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Navigation') }}">
                <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-gray-950/50 backdrop-blur-xs" @click="sidebarOpen = false"></div>

                <div x-show="sidebarOpen"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="-translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="-translate-x-full"
                     class="fixed inset-y-0 left-0 flex w-72 max-w-[85%] flex-col bg-surface shadow-xl">
                    <div class="flex h-16 shrink-0 items-center justify-between border-b border-line px-4">
                        @include('layouts.partials.brand')
                        <button type="button" class="rounded-lg p-2 text-ink-muted hover:bg-surface-muted hover:text-ink" @click="sidebarOpen = false">
                            <span class="sr-only">{{ __('Close navigation') }}</span>
                            <x-icon name="x" class="size-6" />
                        </button>
                    </div>
                    @include('layouts.partials.sidebar-nav')
                </div>
            </div>

            {{-- Desktop sidebar --}}
            <aside class="hidden lg:fixed lg:inset-y-0 lg:z-30 lg:flex lg:w-64 lg:flex-col lg:border-r lg:border-line lg:bg-surface">
                <div class="flex h-16 shrink-0 items-center border-b border-line px-5">
                    @include('layouts.partials.brand')
                </div>
                @include('layouts.partials.sidebar-nav')
            </aside>

            <div class="lg:pl-64">
                {{-- Top bar --}}
                <div class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-surface/85 px-4 backdrop-blur-md sm:px-6 lg:px-8">
                    <button type="button" class="-ml-2 rounded-lg p-2 text-ink-muted hover:bg-surface-muted hover:text-ink lg:hidden" @click="sidebarOpen = true">
                        <span class="sr-only">{{ __('Open navigation') }}</span>
                        <x-icon name="menu" class="size-6" />
                    </button>

                    <div class="min-w-0 flex-1">
                        @isset($breadcrumbs)
                            <nav aria-label="{{ __('Breadcrumb') }}" class="hidden truncate text-xs text-ink-muted sm:block">{{ $breadcrumbs }}</nav>
                        @endisset
                        @isset($header)
                            <h1 class="truncate text-lg font-semibold tracking-tight text-ink">{{ $header }}</h1>
                        @endisset
                    </div>

                    {{-- Notifications (Laravel database notifications) --}}
                    <x-dropdown align="right" width="w-80">
                        <x-slot name="trigger">
                            <button type="button" class="relative rounded-full p-2 text-ink-muted transition hover:bg-surface-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-brand-500">
                                <span class="sr-only">{{ trans_choice('View notifications (:count unread)|View notifications (:count unread)', $unreadNotificationCount, ['count' => $unreadNotificationCount]) }}</span>
                                <x-icon name="bell" class="size-6" />
                                @if ($unreadNotificationCount > 0)
                                    <span class="absolute right-1 top-1 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[0.625rem] font-semibold leading-4 text-white ring-2 ring-surface" aria-hidden="true">{{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}</span>
                                @endif
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="flex items-center justify-between border-b border-line px-4 py-2.5">
                                <p class="text-sm font-semibold text-ink">{{ __('Notifications') }}</p>
                                @if ($unreadNotificationCount > 0)
                                    <form method="POST" action="{{ route('notifications.read-all') }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-brand-700 hover:text-brand-900 dark:text-brand-400 dark:hover:text-brand-300">{{ __('Mark all as read') }}</button>
                                    </form>
                                @endif
                            </div>
                            @forelse ($recentNotifications as $notification)
                                <a href="{{ route('notifications.open', $notification->id) }}" @class(['flex gap-3 border-b border-line px-4 py-3 text-sm transition hover:bg-surface-muted', 'bg-brand-50/60 dark:bg-brand-500/5' => $notification->unread()])>
                                    <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-brand-500' => $notification->unread(), 'bg-transparent' => ! $notification->unread()]) aria-hidden="true"></span>
                                    <span class="min-w-0">
                                        <span @class(['block', 'font-medium text-ink' => $notification->unread(), 'text-ink-muted' => ! $notification->unread()])>{{ $notification->data['message'] ?? __('Notification') }}</span>
                                        <span class="mt-0.5 block text-xs text-ink-subtle">{{ $notification->created_at->diffForHumans() }}</span>
                                    </span>
                                </a>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-ink-muted">{{ __('You have no notifications.') }}</p>
                            @endforelse
                            <a href="{{ route('notifications.index') }}" class="block px-4 py-2.5 text-center text-sm font-medium text-brand-700 hover:bg-surface-muted dark:text-brand-400">{{ __('View all notifications') }}</a>
                        </x-slot>
                    </x-dropdown>

                    <x-dropdown align="right" width="w-64">
                        <x-slot name="trigger">
                            <button type="button" class="flex items-center gap-2 rounded-lg p-1.5 text-sm transition hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-brand-500">
                                <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-800 dark:bg-brand-500/15 dark:text-brand-300" aria-hidden="true">
                                    {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                                </span>
                                <span class="hidden text-left sm:block">
                                    <span class="block max-w-40 truncate font-medium text-ink">{{ Auth::user()->name }}</span>
                                    <span class="block text-xs text-ink-muted">{{ Auth::user()->primaryRole()?->label() }}</span>
                                </span>
                                <x-icon name="chevron-down" class="hidden size-4 text-ink-subtle sm:block" />
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="border-b border-line px-4 py-2.5 sm:hidden">
                                <p class="truncate text-sm font-medium text-ink">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-ink-muted">{{ Auth::user()->primaryRole()?->label() }}</p>
                            </div>
                            <x-dropdown-link :href="route('profile.edit')">
                                <x-icon name="user" class="size-4 text-ink-subtle" />
                                {{ __('My Account') }}
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm leading-5 text-ink transition hover:bg-surface-muted focus:bg-surface-muted focus:outline-hidden">
                                    <x-icon name="logout" class="size-4 text-ink-subtle" />
                                    {{ __('Log Out') }}
                                </button>
                            </form>
                            <div class="mt-1 border-t border-line">
                                @include('layouts.partials.theme-switcher')
                            </div>
                        </x-slot>
                    </x-dropdown>
                </div>

                <main id="main-content" class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mx-auto max-w-7xl space-y-6">
                        @if (session('status'))
                            <x-alert type="success" dismissible>{{ session('status') }}</x-alert>
                        @endif

                        @if (session('error'))
                            <x-alert type="error">{{ session('error') }}</x-alert>
                        @endif

                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
