<x-app-layout :title="__('Dashboard')">
    <x-slot name="header">{{ __('My Dashboard') }}</x-slot>

    <x-page-header :title="__('Welcome back, :name.', ['name' => Auth::user()->name])" :description="now()->format('l, F j, Y')">
        @if ($employee && Auth::user()->can(\App\Enums\Permission::FileLeave))
            <x-slot name="actions">
                <x-button-link :href="route('leave.requests.create')">
                    <x-icon name="plus" class="size-4" />
                    {{ __('File Leave') }}
                </x-button-link>
            </x-slot>
        @endif
    </x-page-header>

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

    <div class="grid gap-6 lg:grid-cols-2">
        @include('dashboard.partials.my-payslip')
        @include('dashboard.partials.announcements')
    </div>
</x-app-layout>
