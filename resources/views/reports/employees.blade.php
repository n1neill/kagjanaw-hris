<x-app-layout :title="__('Employee List')">
    <x-slot name="header">{{ __('Employee List') }}</x-slot>

    <x-card :title="__('Employee List')" :description="$subtitle">
        @if ($hasDepartment)
            <x-slot name="actions">
                <x-export-links route="reports.employees.export" :parameters="array_filter([
                    'department' => $seesAllDepartments ? $departmentId : null,
                    'employment_type' => $employmentTypeId,
                    'status' => $status,
                ])" />
            </x-slot>
        @endif

        @unless ($hasDepartment)
            <p class="text-sm text-gray-600">{{ __('Your account is not linked to an employee record with a department yet. Please contact the HR office.') }}</p>
        @else
            <form method="GET" action="{{ route('reports.employees') }}" class="mb-6 flex flex-wrap items-end gap-3">
                @if ($seesAllDepartments)
                    <div class="w-full sm:w-64">
                        <x-input-label for="department" :value="__('Office')" />
                        <x-select-input id="department" name="department" class="mt-1" :options="$departments" :selected="$departmentId" :placeholder="__('All offices')" />
                    </div>
                @endif
                <div class="w-full sm:w-56">
                    <x-input-label for="employment_type" :value="__('Employment type')" />
                    <x-select-input id="employment_type" name="employment_type" class="mt-1" :options="$employmentTypes" :selected="$employmentTypeId" :placeholder="__('All types')" />
                </div>
                <div class="w-full sm:w-48">
                    <x-input-label for="status" :value="__('Status')" />
                    <x-select-input id="status" name="status" class="mt-1" :options="$statuses" :selected="$status" />
                </div>
                <x-secondary-button type="submit">{{ __('Show') }}</x-secondary-button>
            </form>

            <x-data-table :empty="__('No employees match these filters.')">
                <thead>
                    <tr>
                        <th>{{ __('Employee ID') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Position') }}</th>
                        <th>{{ __('Office') }}</th>
                        <th>{{ __('Employment type') }}</th>
                        <th>{{ __('SG') }}</th>
                        <th>{{ __('Date hired') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $employee)
                        <tr>
                            <td class="font-mono">{{ $employee->employee_number }}</td>
                            <td>{{ $employee->sortable_name }}</td>
                            <td>{{ $employee->position->title }}</td>
                            <td>{{ $employee->department->name }}</td>
                            <td>{{ $employee->employmentType->name }}</td>
                            <td>{{ $employee->salary_grade ? $employee->salary_grade.'-'.$employee->salary_step : '—' }}</td>
                            <td data-order="{{ $employee->date_hired->format('Y-m-d') }}">{{ $employee->date_hired->format('M j, Y') }}</td>
                            <td>{{ $employee->employment_status->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
        @endunless
    </x-card>
</x-app-layout>
