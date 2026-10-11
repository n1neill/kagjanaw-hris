<x-app-layout :title="__('Daily Time Record')">
    <x-slot name="header">{{ __('Daily Time Record') }}</x-slot>

    <x-card :title="$employee->full_name" :description="$employee->employee_number.' · '.$employee->position->title.' · '.$employee->department->name">
        <x-slot name="actions">
            <x-button-link :href="route('attendance.dtr.pdf', [$employee, 'month' => $month->format('Y-m')])" variant="secondary" target="_blank" rel="noopener">
                <x-icon name="document" class="size-4" />
                {{ __('Print DTR (Form 48)') }}
            </x-button-link>
        </x-slot>

        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <x-month-picker :month="$month" :action="route('attendance.dtr.show', $employee)" />

            @if ($canManage)
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('attendance.dtr.reprocess', [$employee, 'month' => $month->format('Y-m')]) }}">
                        @csrf
                        <x-secondary-button type="submit">{{ __('Recompute from logs') }}</x-secondary-button>
                    </form>
                    <form method="POST" action="{{ route('attendance.dtr.validate', [$employee, 'month' => $month->format('Y-m')]) }}">
                        @csrf
                        <x-primary-button>{{ __('Validate :month', ['month' => $month->format('F')]) }}</x-primary-button>
                    </form>
                </div>
            @endif
        </div>

        @if ($isValidated)
            <p class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">{{ __('Days of this month have been validated by HR.') }}</p>
        @endif

        @include('attendance.partials.dtr-table')
    </x-card>
</x-app-layout>
