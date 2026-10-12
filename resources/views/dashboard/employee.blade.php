<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('My Dashboard') }}</x-slot>

    <p class="text-sm text-gray-600">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

    @include('dashboard.partials.my-attendance')

    @include('dashboard.partials.my-leave')

    @if ($employee)
        <x-card :title="__('My Record')">
            <x-slot name="actions">
                <x-button-link :href="route('my-record')" variant="secondary">{{ __('View my record') }}</x-button-link>
            </x-slot>
            <dl class="grid gap-4 sm:grid-cols-3">
                <x-detail :label="__('Employee ID')"><span class="font-mono">{{ $employee->employee_number }}</span></x-detail>
                <x-detail :label="__('Position')">{{ $employee->position->title }}</x-detail>
                <x-detail :label="__('Department')">{{ $employee->department->name }}</x-detail>
            </dl>
        </x-card>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        @include('dashboard.partials.my-payslip')
        <x-upcoming-module :title="__('Announcements')" icon="megaphone">
            {{ __('News and memos from the HR office (Phase 6).') }}
        </x-upcoming-module>
    </div>
</x-app-layout>
