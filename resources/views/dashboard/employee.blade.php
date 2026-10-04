<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('My Dashboard') }}</x-slot>

    <p class="text-sm text-gray-600">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-upcoming-module :title="__('Today\'s attendance')" icon="clock">
            {{ __('Your time in and time out for today (Phase 3).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('Leave balance')" icon="calendar">
            {{ __('Your vacation and sick leave credits (Phase 4).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('Latest payslip')" icon="banknotes">
            {{ __('Download your most recent payslip (Phase 5).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('Announcements')" icon="megaphone">
            {{ __('News and memos from the HR office (Phase 6).') }}
        </x-upcoming-module>
    </div>
</x-app-layout>
