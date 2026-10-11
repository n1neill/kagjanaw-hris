<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('HR Dashboard') }}</x-slot>

    <p class="text-sm text-gray-600">{{ __('Welcome back, :name.', ['name' => Auth::user()->name]) }}</p>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Employees in service')" :value="$employeesInService" icon="users" />
        <x-stat-card :label="__('Separated employees')" :value="$separatedEmployees" />
        <div class="flex items-center justify-center rounded-lg border border-gray-200 bg-white p-4 shadow-xs">
            <x-button-link :href="route('employees.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Employee') }}
            </x-button-link>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-upcoming-module :title="__('Pending leave approvals')" icon="calendar">
            {{ __('Leave requests endorsed by department heads (Phase 4).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('Payroll status')" icon="banknotes">
            {{ __('Current payroll run and its status (Phase 5).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('Biometric sync')" icon="clock">
            {{ __('Last attendance sync and flagged logs (Phase 3).') }}
        </x-upcoming-module>
        <x-upcoming-module :title="__('Announcements')" icon="megaphone">
            {{ __('Post announcements to all employees (Phase 6).') }}
        </x-upcoming-module>
    </div>

    <x-card :title="__('Reference data')" :description="__('Keep offices, positions and employment types up to date before encoding employee records.')">
        <div class="flex flex-wrap gap-2">
            <x-button-link :href="route('reference-data.departments.index')" variant="secondary">{{ __('Departments') }}</x-button-link>
            <x-button-link :href="route('reference-data.positions.index')" variant="secondary">{{ __('Positions') }}</x-button-link>
            <x-button-link :href="route('reference-data.employment-types.index')" variant="secondary">{{ __('Employment Types') }}</x-button-link>
        </div>
    </x-card>
</x-app-layout>
