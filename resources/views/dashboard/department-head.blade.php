<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('Department Dashboard') }}</x-slot>

    <p class="text-sm text-gray-600">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-upcoming-module :title="__('Pending endorsements')" icon="calendar">
            {{ __('Leave requests from your department awaiting your endorsement (Phase 4).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('Team attendance today')" icon="clock">
            {{ __('Who is in, late or absent in your department (Phase 3).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('My leave balance')" icon="briefcase">
            {{ __('Your vacation and sick leave credits (Phase 4).') }}
        </x-upcoming-module>
    </div>
</x-app-layout>
