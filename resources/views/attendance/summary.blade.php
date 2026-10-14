@use('App\Services\Attendance\AttendanceReportService', 'Report')

<x-app-layout :title="__('Attendance Summary')">
    <x-slot name="header">{{ $canManage ? __('Attendance Summary') : __('Department Attendance') }}</x-slot>

    <x-card :title="$month->format('F Y').($department ? ' — '.$department->name : '')"
            :description="__('Totals from the Daily Time Records. Absences are in days; late and undertime in hours and minutes.')">
        @if ($hasDepartment)
            <x-slot name="actions">
                <x-export-links route="attendance.summary.export" :parameters="array_filter(['month' => $month->format('Y-m'), 'department' => $canManage ? $department?->id : null])" />
            </x-slot>
        @endif

        @if (! $hasDepartment)
            <p class="text-sm text-ink-muted">{{ __('Your account is not linked to an employee record with a department yet. Please contact the HR office.') }}</p>
        @else
            <x-month-picker :month="$month" :action="route('attendance.summary')" class="mb-6">
                @if ($canManage)
                    <div class="w-full sm:w-72">
                        <x-input-label for="department" :value="__('Department')" />
                        <x-select-input id="department" name="department" class="mt-1" :options="$departments" :selected="$department?->id" :placeholder="__('All departments')" />
                    </div>
                @endif
            </x-month-picker>

            <x-data-table :empty="__('No employees for this month.')">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        @if ($canManage)
                            <th>{{ __('Dept.') }}</th>
                        @endif
                        <th class="text-right">{{ __('Present') }}</th>
                        <th class="text-right">{{ __('Absences') }}</th>
                        <th class="text-right">{{ __('Times late') }}</th>
                        <th class="text-right">{{ __('Late') }}</th>
                        <th class="text-right">{{ __('Undertime') }}</th>
                        <th class="text-right">{{ __('Missing punches') }}</th>
                        <th data-orderable="false"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                <span class="font-medium text-ink">{{ $row['employee']->sortable_name }}</span>
                                <span class="block font-mono text-xs text-ink-muted">{{ $row['employee']->employee_number }}</span>
                            </td>
                            @if ($canManage)
                                <td>{{ $row['employee']->department->code }}</td>
                            @endif
                            <td class="text-right" data-order="{{ $row['present_days'] }}">{{ $row['present_days'] }} / {{ $row['working_days'] }}</td>
                            <td class="text-right">{{ $row['absent_days'] ?: '—' }}</td>
                            <td class="text-right">{{ $row['late_count'] ?: '—' }}</td>
                            <td class="text-right" data-order="{{ $row['late_minutes'] }}">{{ Report::formatMinutes($row['late_minutes']) }}</td>
                            <td class="text-right" data-order="{{ $row['undertime_minutes'] }}">{{ Report::formatMinutes($row['undertime_minutes']) }}</td>
                            <td class="text-right">
                                @if ($row['missing_punch_days'])
                                    <x-badge color="yellow">{{ $row['missing_punch_days'] }}</x-badge>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('attendance.dtr.show', [$row['employee'], 'month' => $month->format('Y-m')]) }}" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('DTR') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
        @endif
    </x-card>
</x-app-layout>
