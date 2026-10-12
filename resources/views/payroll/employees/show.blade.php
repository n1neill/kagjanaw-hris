@use('App\Enums\PayBasis')
@use('App\Services\Payroll\Money')

<x-app-layout :title="__('Pay Setup')">
    <x-slot name="header">{{ __('Pay Setup') }}</x-slot>

    @if ($errors->any())
        <div role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ __('Please correct the highlighted fields below.') }}
        </div>
    @endif

    <x-card :title="$employee->full_name" :description="$employee->position->title.' · '.$employee->department->name">
        <x-slot name="actions">
            <x-button-link :href="route('employees.show', $employee)" variant="secondary">{{ __('201 File') }}</x-button-link>
        </x-slot>

        <dl class="grid gap-4 sm:grid-cols-4">
            <x-detail :label="__('Employment type')">{{ $employee->employmentType->name }}</x-detail>
            <x-detail :label="__('Paid by')">{{ $payBasis->label() }}</x-detail>
            @if ($payBasis === PayBasis::SalaryGrade)
                <x-detail :label="__('Salary grade / step')">
                    @if ($employee->salary_grade)
                        SG {{ $employee->salary_grade }}, {{ __('Step') }} {{ $employee->salary_step }}
                    @endif
                </x-detail>
                <x-detail :label="__('Monthly salary (LGU rate today)')">{{ $monthlySalary !== null ? Money::format($monthlySalary) : __('Not in the Salary Schedule') }}</x-detail>
            @else
                <x-detail :label="__('Daily rate')">{{ $employee->daily_rate !== null ? Money::format($employee->daily_rate) : __('Not set') }}</x-detail>
            @endif
        </dl>
        <p class="mt-3 text-xs text-gray-500">{{ __('Grade, step and daily rate are edited on the employee\'s 201 file.') }}</p>
    </x-card>

    @if ($payBasis === PayBasis::SalaryGrade)
        <x-card :title="__('Allowances')" :description="__('Allowances covering every salary-grade employee apply automatically. Assign others here, or assign a covering allowance to give this employee a different amount or period.')">
            @if ($currentAllowances->isNotEmpty())
                <p class="mb-3 text-sm text-gray-700">
                    {{ __('This month:') }}
                    {{ $currentAllowances->map(fn ($allowance) => $allowance['type']->name.' '.Money::format($allowance['monthly']))->implode(' · ') }}
                </p>
            @endif

            @if ($employee->allowances->isNotEmpty())
                <table class="mb-4 w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="py-2">{{ __('Assigned allowance') }}</th>
                            <th class="py-2 text-right">{{ __('Monthly') }}</th>
                            <th class="py-2">{{ __('Period') }}</th>
                            <th class="py-2 text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employee->allowances as $assignment)
                            <tr class="border-b border-gray-100">
                                <td class="py-2 font-medium text-gray-900">{{ $assignment->allowanceType->name }}</td>
                                <td class="py-2 text-right tabular-nums">{{ Money::format($assignment->monthly_amount ?? $assignment->allowanceType->monthly_amount) }}</td>
                                <td class="py-2">{{ $assignment->starts_on?->format('M j, Y') ?? __('Start') }} – {{ $assignment->ends_on?->format('M j, Y') ?? __('until removed') }}</td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('payroll.employees.allowances.destroy', [$employee, $assignment]) }}" onsubmit="return confirm(@js(__('Remove this allowance?')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">{{ __('Remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($availableAllowances->isNotEmpty())
                <form method="POST" action="{{ route('payroll.employees.allowances.store', $employee) }}" class="grid gap-4 sm:grid-cols-5 sm:items-end">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-input-label for="allowance_type_id" :value="__('Assign allowance')" />
                        <x-select-input id="allowance_type_id" name="allowance_type_id" class="mt-1" :options="$availableAllowances" :selected="old('allowance_type_id')" :placeholder="__('Select allowance')" required />
                        <x-input-error :messages="$errors->get('allowance_type_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="allowance_amount" :value="__('Monthly (blank = standard)')" />
                        <x-text-input id="allowance_amount" name="allowance_amount" inputmode="decimal" class="mt-1" :value="old('allowance_amount')" />
                        <x-input-error :messages="$errors->get('allowance_amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="allowance_starts_on" :value="__('From (optional)')" />
                        <x-text-input id="allowance_starts_on" name="allowance_starts_on" type="date" class="mt-1" :value="old('allowance_starts_on')" />
                        <x-input-error :messages="$errors->get('allowance_starts_on')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="allowance_ends_on" :value="__('Until (optional)')" />
                        <x-text-input id="allowance_ends_on" name="allowance_ends_on" type="date" class="mt-1" :value="old('allowance_ends_on')" />
                        <x-input-error :messages="$errors->get('allowance_ends_on')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-5">
                        <x-secondary-button type="submit">{{ __('Assign') }}</x-secondary-button>
                    </div>
                </form>
            @endif
        </x-card>
    @endif

    <x-card :title="__('Loans and other deductions')" :description="__('Half the monthly amount is deducted each payroll, in priority order, until the end date or until the total is paid. Deductions stop where net pay would fall below the minimum.')">
        @if ($deductions->isNotEmpty())
            <div class="mb-6 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-3">{{ __('Deduction') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Monthly') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Total') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Deducted so far') }}</th>
                            <th class="px-3 py-2">{{ __('Period') }}</th>
                            <th class="px-3 py-2">{{ __('Status') }}</th>
                            <th class="py-2 pl-3 text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deductions as $deduction)
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-3 font-medium text-gray-900">
                                    {{ $deduction->name }}
                                    @if ($deduction->reference)
                                        <span class="block text-xs font-normal text-gray-500">{{ $deduction->reference }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ Money::format($deduction->monthly_amount) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $deduction->total_amount !== null ? Money::format($deduction->total_amount) : '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ Money::format($deducted[$deduction->id]) }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $deduction->starts_on->format('M j, Y') }} – {{ $deduction->ends_on?->format('M j, Y') ?? __('open') }}</td>
                                <td class="px-3 py-2"><x-status-badge :active="$deduction->is_active" /></td>
                                <td class="py-2 pl-3 text-right">
                                    <a href="{{ route('payroll.employees.deductions.edit', [$employee, $deduction]) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Edit') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form method="POST" action="{{ route('payroll.employees.deductions.store', $employee) }}" class="space-y-4">
            @csrf
            <h3 class="text-sm font-semibold text-gray-900">{{ __('Add a deduction') }}</h3>
            @include('payroll.employees.partials.deduction-fields', ['deduction' => null])
            <x-secondary-button type="submit">{{ __('Add Deduction') }}</x-secondary-button>
        </form>
    </x-card>

    <x-card :title="__('Recent payslips')">
        @if ($payslips->isEmpty())
            <p class="text-sm text-gray-500">{{ __('No payroll has included this employee yet.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                        <th class="py-2">{{ __('Period') }}</th>
                        <th class="py-2">{{ __('Status') }}</th>
                        <th class="py-2 text-right">{{ __('Net pay') }}</th>
                        <th class="py-2 text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payslips as $payslip)
                        <tr class="border-b border-gray-100">
                            <td class="py-2"><a href="{{ route('payroll.runs.show', $payslip->run) }}" class="font-medium text-brand-700 hover:text-brand-900">{{ $payslip->run->period->label() }}</a></td>
                            <td class="py-2"><x-badge :color="$payslip->run->status->badgeColor()">{{ $payslip->run->status->label() }}</x-badge></td>
                            <td class="py-2 text-right tabular-nums">{{ Money::format($payslip->net_pay) }}</td>
                            <td class="py-2 text-right"><a href="{{ route('payroll.payslips.show', $payslip) }}" target="_blank" rel="noopener" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Payslip') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-app-layout>
