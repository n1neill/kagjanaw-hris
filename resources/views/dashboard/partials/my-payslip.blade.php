@use('App\Services\Payroll\Money')

{{-- The signed-in employee's latest released payslip. Expects $myLatestPayslip. --}}
<div class="rounded-xl border border-line bg-surface p-4 shadow-xs">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-ink">{{ __('Latest payslip') }}</h3>
        <x-icon name="banknotes" class="size-5 text-brand-600 dark:text-brand-400" />
    </div>
    @if ($myLatestPayslip)
        <p class="mt-2 text-sm text-ink-muted">{{ $myLatestPayslip->run->period->label() }}</p>
        <p class="mt-1 text-2xl font-semibold text-ink">₱ {{ Money::format($myLatestPayslip->net_pay) }}</p>
        <p class="text-xs text-ink-muted">{{ __('Net pay') }}</p>
        <div class="mt-3 flex flex-wrap gap-4">
            <a href="{{ route('payroll.payslips.show', $myLatestPayslip) }}" target="_blank" rel="noopener" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('Download') }}</a>
            <a href="{{ route('payroll.payslips.my') }}" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('All payslips') }}</a>
        </div>
    @else
        <p class="mt-2 text-sm text-ink-muted">{{ __('No payslip has been released to you yet.') }}</p>
    @endif
</div>
