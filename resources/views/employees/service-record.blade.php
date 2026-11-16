@use('App\Services\Payroll\Money')

{{--
    The CSC service record. HR ($canManage) adds, corrects and removes entries;
    the employee only views and prints it.
--}}
<x-app-layout :title="$isOwnRecord ? __('My Service Record') : __('Service Record of :name', ['name' => $employee->full_name])">
    <x-slot name="header">{{ $isOwnRecord ? __('My Service Record') : __('Service Record') }}</x-slot>

    <section class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-line bg-linear-to-r from-brand-50 via-surface to-surface p-4 shadow-xs sm:p-6 dark:from-brand-500/10">
        <div>
            <h2 class="text-xl font-semibold text-ink">{{ $employee->full_name }}</h2>
            <p class="text-sm text-ink-muted">{{ $employee->position->title }} · {{ $employee->department->name }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <x-badge color="brand" class="font-mono">{{ $employee->employee_number }}</x-badge>
                <x-badge>{{ __('Born :details', ['details' => collect([$employee->birth_date->format('F j, Y'), $employee->birth_place])->filter()->join(', ')]) }}</x-badge>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <x-button-link :href="$isOwnRecord ? route('my-record') : route('employees.show', $employee)" variant="secondary">
                {{ $isOwnRecord ? __('My Record') : __('Back to record') }}
            </x-button-link>
            <x-button-link :href="route('employees.service-record.pdf', $employee)" target="_blank" rel="noopener" variant="secondary">
                <x-icon name="document" class="size-4" />
                {{ __('Print / PDF') }}
            </x-button-link>
            @if ($canManage)
                <x-button-link :href="route('employees.service-record.create', $employee)">
                    <x-icon name="plus" class="size-4" />
                    {{ __('Add Entry') }}
                </x-button-link>
            @endif
        </div>
    </section>

    <x-card :title="__('Record of Service')" :description="$canManage
        ? __('Each row is one period of service, oldest first. Leave the end date blank for the current appointment.')
        : __('Kept by the HR office. If an entry is wrong or missing, please contact the HR office.')">
        <x-data-table :empty="__('No service record entries yet.')">
            <thead>
                <tr>
                    <th>{{ __('From') }}</th>
                    <th>{{ __('To') }}</th>
                    <th>{{ __('Designation') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Salary (annual)') }}</th>
                    <th>{{ __('Station / Place of assignment') }}</th>
                    <th>{{ __('Branch') }}</th>
                    <th>{{ __('LWOP') }}</th>
                    <th>{{ __('Separation') }}</th>
                    <th>{{ __('Remarks') }}</th>
                    @if ($canManage)
                        <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td data-order="{{ $entry->service_from->toDateString() }}" class="whitespace-nowrap">{{ $entry->service_from->format('M j, Y') }}</td>
                        <td data-order="{{ $entry->service_to?->toDateString() ?? '9999-12-31' }}" class="whitespace-nowrap">
                            @if ($entry->service_to)
                                {{ $entry->service_to->format('M j, Y') }}
                            @else
                                <x-badge color="green">{{ __('Present') }}</x-badge>
                            @endif
                        </td>
                        <td class="font-medium text-ink">{{ $entry->designation }}</td>
                        <td>{{ $entry->status }}</td>
                        <td class="whitespace-nowrap text-right font-mono" data-order="{{ $entry->annual_salary ?? 0 }}">
                            {{ $entry->annual_salary !== null ? Money::format($entry->annual_salary) : '' }}
                        </td>
                        <td>{{ $entry->station }}</td>
                        <td>{{ $entry->branch }}</td>
                        <td>{{ $entry->leave_without_pay }}</td>
                        <td>
                            @if ($entry->separation_date)
                                <span class="whitespace-nowrap">{{ $entry->separation_date->format('M j, Y') }}</span>
                                <span class="block text-xs text-ink-muted">{{ $entry->separation_cause }}</span>
                            @endif
                        </td>
                        <td>{{ $entry->remarks }}</td>
                        @if ($canManage)
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('employees.service-record.edit', [$employee, $entry]) }}" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route('employees.service-record.destroy', [$employee, $entry]) }}" data-confirm="{{ __('Delete this service record entry?') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
