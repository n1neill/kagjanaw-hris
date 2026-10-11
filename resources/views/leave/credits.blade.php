<x-app-layout :title="__('Leave Credits')">
    <x-slot name="header">{{ __('Leave Credits') }}</x-slot>

    <x-card :title="__('Employees in service')" :description="__('Current vacation and sick leave balances. Open an employee\'s leave card to see every entry, post an adjustment, or issue a Certificate of Leave Credits.')">
        <x-slot name="actions">
            <x-button-link :href="route('leave.posting.index')" variant="secondary">{{ __('Monthly Posting') }}</x-button-link>
            <x-button-link :href="route('leave.opening-balances.create')" variant="secondary">{{ __('Import Opening Balances') }}</x-button-link>
        </x-slot>

        <x-data-table :empty="__('No employees in service.')">
            <thead>
                <tr>
                    <th>{{ __('Employee') }}</th>
                    <th>{{ __('Office') }}</th>
                    <th class="text-right">{{ __('Vacation') }}</th>
                    <th class="text-right">{{ __('Sick') }}</th>
                    <th>{{ __('Credits posted through') }}</th>
                    <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($employees as $employee)
                    @php($employeeBalances = $balances->get($employee->id))
                    <tr>
                        <td>
                            <span class="font-medium text-gray-900">{{ $employee->sortable_name }}</span>
                            <span class="block font-mono text-xs text-gray-500">{{ $employee->employee_number }}</span>
                        </td>
                        <td>{{ $employee->department?->name }}</td>
                        @if ($employee->earnsLeaveCredits())
                            <td class="text-right tabular-nums">{{ $employeeBalances?->get($vacationTypeId) ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $employeeBalances?->get($sickTypeId) ?? '—' }}</td>
                            <td data-order="{{ $lastPosted->get($employee->id) ?? '' }}">
                                {{ $lastPosted->get($employee->id) ? \Carbon\CarbonImmutable::parse($lastPosted->get($employee->id))->format('F Y') : __('Not yet') }}
                            </td>
                        @else
                            <td class="text-right text-gray-400">—</td>
                            <td class="text-right text-gray-400">—</td>
                            <td class="text-xs text-gray-500">{{ __(':type: no leave credits', ['type' => $employee->employmentType?->name]) }}</td>
                        @endif
                        <td class="text-right">
                            <a href="{{ route('leave.card', $employee) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Leave card') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
