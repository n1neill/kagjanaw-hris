@use('App\Services\Payroll\Money')

{{-- The signed-in employee's latest released payslip. Expects $myLatestPayslip. --}}
<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-xs">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-700">{{ __('Latest payslip') }}</h3>
        <x-icon name="banknotes" class="size-5 text-brand-600" />
    </div>
    @if ($myLatestPayslip)
        <p class="mt-2 text-sm text-gray-600">{{ $myLatestPayslip->run->period->label() }}</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">₱ {{ Money::format($myLatestPayslip->net_pay) }}</p>
        <p class="text-xs text-gray-500">{{ __('Net pay') }}</p>
        <div class="mt-3 flex flex-wrap gap-4">
            <a href="{{ route('payroll.payslips.show', $myLatestPayslip) }}" target="_blank" rel="noopener" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Download') }}</a>
            <a href="{{ route('payroll.payslips.my') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('All payslips') }}</a>
        </div>
    @else
        <p class="mt-2 text-sm text-gray-500">{{ __('No payslip has been released to you yet.') }}</p>
    @endif
</div>
