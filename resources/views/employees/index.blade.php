<x-app-layout :title="$canManage ? __('Employees') : __('My Department')">
    <x-slot name="header">{{ $canManage ? __('Employees') : __('My Department') }}</x-slot>

    <x-card
        :title="$canManage ? __('Employee records') : ($ownDepartment?->name ?? __('My Department'))"
        :description="$canManage ? __('Adding an employee also creates their login account.') : __('Employees in your department.')"
    >
        @if ($canManage)
            <x-slot name="actions">
                <x-button-link :href="route('employees.create')">
                    <x-icon name="plus" class="size-4" />
                    {{ __('New Employee') }}
                </x-button-link>
            </x-slot>
        @endif

        @if (! $canManage && $ownDepartment === null)
            <p class="text-sm text-ink-muted">{{ __('Your account is not linked to an employee record with a department yet. Please contact the HR office.') }}</p>
        @else
            <form method="GET" action="{{ route('employees.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
                @if ($canManage)
                    <div class="w-full sm:w-72">
                        <x-input-label for="filter-department" :value="__('Department')" />
                        <x-select-input id="filter-department" name="department" class="mt-1" :options="$departments" :selected="$selectedDepartment" :placeholder="__('All departments')" />
                    </div>
                @endif
                <div class="w-full sm:w-48">
                    <x-input-label for="filter-status" :value="__('Status')" />
                    <x-select-input id="filter-status" name="status" class="mt-1" :options="$statuses" :selected="$selectedStatus" />
                </div>
                <x-secondary-button type="submit">{{ __('Apply') }}</x-secondary-button>
            </form>

            <x-data-table :empty="__('No employees match these filters.')">
                <thead>
                    <tr>
                        <th>{{ __('Employee ID') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Position') }}</th>
                        @if ($canManage)
                            <th>{{ __('Department') }}</th>
                        @endif
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $employee)
                        <tr>
                            <td class="font-mono text-xs">{{ $employee->employee_number }}</td>
                            <td class="font-medium text-ink" data-order="{{ $employee->sortable_name }}">{{ $employee->sortable_name }}</td>
                            <td>{{ $employee->position->title }}</td>
                            @if ($canManage)
                                <td>{{ $employee->department->code }}</td>
                            @endif
                            <td>{{ $employee->employmentType->name }}</td>
                            <td>
                                <x-badge :color="$employee->employment_status->isInService() ? 'green' : 'gray'">{{ $employee->employment_status->label() }}</x-badge>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('employees.show', $employee) }}" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
        @endif
    </x-card>
</x-app-layout>
