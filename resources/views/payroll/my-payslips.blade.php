@use('App\Services\Payroll\Money')

<x-app-layout :title="__('My Payslips')">
    <x-slot name="header">{{ __('My Payslips') }}</x-slot>

    <x-card :title="__('Payslips')" :description="__('Payslips appear here once HR releases the payroll.')">
        @if ($employee === null)
            <p class="text-sm text-ink-muted">{{ __('Your account is not linked to an employee record.') }}</p>
        @else
            <x-data-table :empty="__('No payslips have been released to you yet.')">
                <thead>
                    <tr>
                        <th>{{ __('Pay period') }}</th>
                        <th>{{ __('Payroll') }}</th>
                        <th class="text-right">{{ __('Gross') }}</th>
                        <th class="text-right">{{ __('Deductions') }}</th>
                        <th class="text-right">{{ __('Net pay') }}</th>
                        <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payslips as $payslip)
                        <tr>
                            <td class="font-medium text-ink" data-order="{{ $payslip->run->period->start_date->toDateString() }}">{{ $payslip->run->period->label() }}</td>
                            <td>{{ $payslip->run->pay_basis->payrollLabel() }}</td>
                            <td class="text-right tabular-nums">{{ Money::format($payslip->gross_pay) }}</td>
                            <td class="text-right tabular-nums">{{ Money::format($payslip->total_deductions) }}</td>
                            <td class="text-right font-medium tabular-nums">{{ Money::format($payslip->net_pay) }}</td>
                            <td class="text-right">
                                <a href="{{ route('payroll.payslips.show', $payslip) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">
                                    <x-icon name="document" class="size-4" />
                                    {{ __('Download') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
        @endif
    </x-card>
</x-app-layout>
