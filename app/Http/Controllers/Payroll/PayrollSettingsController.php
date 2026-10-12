<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\PayrollSettingsRequest;
use App\Services\Payroll\Money;
use App\Services\Payroll\PayrollSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Payroll settings HR maintains (income class, minimum net pay, rates).
 */
class PayrollSettingsController extends Controller
{
    public function __construct(private PayrollSettings $settings) {}

    public function edit(): View
    {
        return view('payroll.settings', [
            'incomeClass' => $this->settings->incomeClass(),
            'isConfirmed' => $this->settings->isIncomeClassConfirmed(),
            'percentages' => $this->settings->percentages(),
            'minimumNetPay' => Money::toDecimal($this->settings->minimumNetPay()),
            'dailyRateTaxRate' => Money::toDecimal($this->settings->dailyRateTaxRate()),
            'attendanceDeductionsFrom' => $this->settings->savedAttendanceDeductionsFrom(),
            'effectiveAttendanceFrom' => $this->settings->attendanceDeductionsFrom(),
        ]);
    }

    public function update(PayrollSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->settings(), $request->user());

        return redirect()->route('payroll.settings.edit')->with('status', __('Payroll settings saved. They apply to payrolls computed from now on.'));
    }
}
