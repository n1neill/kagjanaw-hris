<x-app-layout :title="__('My Attendance')">
    <x-slot name="header">{{ __('My Attendance') }}</x-slot>

    <x-card :title="$month->format('F Y')" :description="__('Office hours: :am and :pm, with a :grace-minute grace period.', [
        'am' => $schedule->sessionLabel($schedule->am_start, $schedule->am_end),
        'pm' => $schedule->sessionLabel($schedule->pm_start, $schedule->pm_end),
        'grace' => $schedule->grace_minutes,
    ])">
        <x-slot name="actions">
            <x-button-link :href="route('attendance.dtr.pdf', [$employee, 'month' => $month->format('Y-m')])" variant="secondary" target="_blank" rel="noopener">
                <x-icon name="document" class="size-4" />
                {{ __('Print DTR (Form 48)') }}
            </x-button-link>
        </x-slot>

        <x-month-picker :month="$month" :action="route('attendance.my')" class="mb-6" />

        @include('attendance.partials.dtr-table', ['canManage' => false])

        <p class="mt-4 text-xs text-gray-500">{{ __('Think a time is wrong? Ask the HR office to correct it. Corrections are recorded with the reason.') }}</p>
    </x-card>
</x-app-layout>
