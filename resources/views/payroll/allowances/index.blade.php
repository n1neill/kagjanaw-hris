@use('App\Enums\AllowanceCoverage')
@use('App\Services\Payroll\Money')

<x-app-layout :title="__('Allowances')">
    <x-slot name="header">{{ __('Allowances') }}</x-slot>

    <x-card :title="__('Allowances')" :description="__('Paid half per semi-monthly payroll to salary-grade employees. An allowance either covers every salary-grade employee or only those it is assigned to (from an employee\'s pay setup).')">
        <x-slot name="actions">
            <x-button-link :href="route('payroll.allowances.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Allowance') }}
            </x-button-link>
        </x-slot>

        <x-data-table :empty="__('No allowances yet.')">
            <thead>
                <tr>
                    <th>{{ __('Allowance') }}</th>
                    <th>{{ __('Code') }}</th>
                    <th class="text-right">{{ __('Monthly') }}</th>
                    <th>{{ __('Who receives it') }}</th>
                    <th>{{ __('Treatment') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $type->name }}</td>
                        <td class="font-mono text-xs">{{ $type->code }}</td>
                        <td class="text-right tabular-nums">{{ Money::format($type->monthly_amount) }}</td>
                        <td class="text-sm">
                            {{ $type->coverage->label() }}
                            @if ($type->coverage === AllowanceCoverage::Assigned)
                                <span class="block text-xs text-gray-500">{{ trans_choice('{0} nobody yet|{1} :count employee|[2,*] :count employees', $type->assignments_count, ['count' => $type->assignments_count]) }}</span>
                            @endif
                        </td>
                        <td class="text-sm">
                            {{ $type->is_taxable ? __('Taxable') : __('Not taxable') }}
                            @if ($type->reduced_for_unpaid_days)
                                <span class="block text-xs text-gray-500">{{ __('Reduced for days without pay') }}</span>
                            @endif
                        </td>
                        <td><x-status-badge :active="$type->is_active" /></td>
                        <td class="text-right">
                            <a href="{{ route('payroll.allowances.edit', $type) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
