@use('App\Services\Payroll\PayrollSettings')
@php($ordinals = PayrollSettings::classOptions())

<x-app-layout :title="__('Payroll Settings')">
    <x-slot name="header">{{ __('Payroll Settings') }}</x-slot>

    <form method="POST" action="{{ route('payroll.settings.update') }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ __('Please correct the highlighted fields below.') }}
            </div>
        @endif

        <x-card :title="__('LGU income class')" :description="__('LGUs pay a percentage of the national Salary Schedule set by their income class (DBM Local Budget Circulars). Changing it affects payrolls computed from now on.')">
            @unless ($isConfirmed)
                <p class="mb-4 rounded-md border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                    {{ __('The income class has not been confirmed. Payroll uses :class until it is; set the municipality\'s actual class and tick “Confirmed”.', ['class' => $ordinals[$incomeClass] ?? $incomeClass]) }}
                </p>
            @endunless

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="income_class" :value="__('Income class of the municipality')" />
                    <x-select-input id="income_class" name="income_class" class="mt-1" :options="$ordinals" :selected="old('income_class', $incomeClass)" />
                    <x-input-error :messages="$errors->get('income_class')" class="mt-2" />
                </div>
                <div class="flex items-end pb-2">
                    <x-checkbox name="income_class_confirmed" value="1" :checked="old('income_class_confirmed', $isConfirmed)" :label="__('Confirmed')" />
                </div>
            </div>

            <fieldset class="mt-6">
                <legend class="text-sm font-medium text-gray-700">{{ __('Percentage of the national Salary Schedule by class') }}</legend>
                <p class="mt-1 text-xs text-gray-500">{{ __('Seeded from the DBM rates for municipalities. Change one if the municipality adopted a lower rate within its Personal Services limit.') }}</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-5">
                    @foreach ($percentages as $class => $percent)
                        <div>
                            <x-input-label :for="'percentages_'.$class" :value="$ordinals[$class]" />
                            <div class="mt-1 flex items-center gap-1">
                                <x-text-input :id="'percentages_'.$class" :name="'percentages['.$class.']'" inputmode="decimal" :value="old('percentages.'.$class, $percent)" required />
                                <span class="text-sm text-gray-500">%</span>
                            </div>
                            <x-input-error :messages="$errors->get('percentages.'.$class)" class="mt-2" />
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </x-card>

        <x-card :title="__('Deductions')">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="minimum_net_pay" :value="__('Minimum monthly net take-home pay (₱)')" />
                    <x-text-input id="minimum_net_pay" name="minimum_net_pay" inputmode="decimal" class="mt-1" :value="old('minimum_net_pay', $minimumNetPay)" required />
                    <p class="mt-1 text-xs text-gray-500">{{ __('Loans and other deductions stop at this amount (half of it per payroll). Government contributions and tax are always deducted.') }}</p>
                    <x-input-error :messages="$errors->get('minimum_net_pay')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="daily_rate_tax_rate" :value="__('Tax withheld from Job Order / COS pay (%)')" />
                    <x-text-input id="daily_rate_tax_rate" name="daily_rate_tax_rate" inputmode="decimal" class="mt-1" :value="old('daily_rate_tax_rate', $dailyRateTaxRate)" required />
                    <p class="mt-1 text-xs text-gray-500">{{ __('Confirm the applicable rate with the BIR (e.g. expanded withholding tax).') }}</p>
                    <x-input-error :messages="$errors->get('daily_rate_tax_rate')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="attendance_deductions_from" :value="__('Deduct absences from (month)')" />
                    <x-text-input id="attendance_deductions_from" name="attendance_deductions_from" type="month" class="mt-1" :value="old('attendance_deductions_from', $attendanceDeductionsFrom)" />
                    <p class="mt-1 text-xs text-gray-500">
                        {{ __('Earlier months were settled before the system. Blank: the month before the first payroll in the system.') }}
                        @if ($effectiveAttendanceFrom)
                            {{ __('Now: :month.', ['month' => $effectiveAttendanceFrom->format('F Y')]) }}
                        @endif
                    </p>
                    <x-input-error :messages="$errors->get('attendance_deductions_from')" class="mt-2" />
                </div>
            </div>
        </x-card>

        <x-primary-button>{{ __('Save Settings') }}</x-primary-button>
    </form>
</x-app-layout>
