@use('App\Services\Payroll\Money')

<x-app-layout :title="__('Payroll')">
    <x-slot name="header">{{ __('Payroll') }}</x-slot>

    <x-card :title="__('New payroll')" :description="__('Semi-monthly: the 1st to the 15th, and the 16th to the end of the month. The regular payroll covers employees paid by salary grade; Job Order and Contract of Service workers have their own payroll.')">
        <form method="POST" action="{{ route('payroll.runs.store') }}" class="flex flex-wrap items-end gap-4">
            @csrf
            <div>
                <x-input-label for="month" :value="__('Month')" />
                <x-text-input id="month" name="month" type="month" class="mt-1 w-44" :value="old('month', $defaultMonth)" required />
                <x-input-error :messages="$errors->get('month')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="half" :value="__('Period')" />
                <x-select-input id="half" name="half" class="mt-1" :options="[1 => __('1st–15th'), 2 => __('16th–end of month')]" :selected="old('half', $defaultHalf)" />
                <x-input-error :messages="$errors->get('half')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="pay_basis" :value="__('Payroll')" />
                <x-select-input id="pay_basis" name="pay_basis" class="mt-1" :options="$payBases" :selected="old('pay_basis')" />
                <x-input-error :messages="$errors->get('pay_basis')" class="mt-2" />
            </div>
            <x-primary-button>{{ __('Create and compute') }}</x-primary-button>
        </form>
        <x-input-error :messages="$errors->get('period')" class="mt-3" />
    </x-card>

    <x-card :title="__('Payroll runs')">
        <x-data-table :empty="__('No payroll has been created yet.')">
            <thead>
                <tr>
                    <th>{{ __('Period') }}</th>
                    <th>{{ __('Payroll') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Employees') }}</th>
                    <th class="text-right">{{ __('Gross') }}</th>
                    <th class="text-right">{{ __('Net pay') }}</th>
                    <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $run)
                    <tr>
                        <td class="font-medium text-gray-900" data-order="{{ $run->period->start_date->toDateString() }}">{{ $run->period->label() }}</td>
                        <td>{{ $run->pay_basis->payrollLabel() }}</td>
                        <td><x-badge :color="$run->status->badgeColor()">{{ $run->status->label() }}</x-badge></td>
                        <td class="text-right tabular-nums">{{ $run->employee_count }}</td>
                        <td class="text-right tabular-nums">{{ Money::format($run->total_gross) }}</td>
                        <td class="text-right tabular-nums">{{ Money::format($run->total_net) }}</td>
                        <td class="text-right">
                            <a href="{{ route('payroll.runs.show', $run) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Open') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
