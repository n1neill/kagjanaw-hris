@use('App\Enums\PayrollRunStatus')
@use('App\Services\Payroll\Money')

@php($status = $run->status)

<x-app-layout :title="$run->title()">
    <x-slot name="header">{{ $run->pay_basis->payrollLabel() }}</x-slot>

    @if ($errors->any())
        <div role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <x-card :title="$run->period->label()" :description="$run->pay_basis->payrollLabel()">
        <x-slot name="actions">
            <x-badge :color="$status->badgeColor()">{{ $status->label() }}</x-badge>
        </x-slot>

        @if ($status === PayrollRunStatus::Computing)
            <p class="text-sm text-gray-600" x-data x-init="setTimeout(() => window.location.reload(), 4000)">
                {{ __('The payroll is being computed. This page refreshes by itself; if it stays here, make sure the queue worker is running, then queue it again.') }}
            </p>
        @endif

        @if ($run->failure_message)
            <p role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $run->failure_message }}</p>
        @endif

        <dl class="grid gap-4 sm:grid-cols-4">
            <x-stat-card :label="__('Employees')" :value="$run->employee_count" />
            <x-stat-card :label="__('Gross pay')" :value="Money::format($run->total_gross)" />
            <x-stat-card :label="__('Deductions')" :value="Money::format($run->total_deductions)" />
            <x-stat-card :label="__('Net pay')" :value="Money::format($run->total_net)" />
        </dl>

        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
            <x-detail :label="__('Computed')">
                @if ($run->computed_at)
                    {{ $run->computed_at->format('M j, Y g:i A') }} · {{ $run->computer?->name }}
                @endif
            </x-detail>
            <x-detail :label="__('Approved')">
                @if ($run->approved_at)
                    {{ $run->approved_at->format('M j, Y g:i A') }} · {{ $run->approver?->name }}
                @endif
            </x-detail>
            <x-detail :label="__('Released')">
                @if ($run->released_at)
                    {{ $run->released_at->format('M j, Y g:i A') }} · {{ $run->releaser?->name }}
                @endif
            </x-detail>
        </dl>

        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4">
            @if ($status->canCompute())
                <form method="POST" action="{{ route('payroll.runs.compute', $run) }}">
                    @csrf
                    <x-secondary-button type="submit">{{ match ($status) { PayrollRunStatus::Draft => __('Compute'), PayrollRunStatus::Computing => __('Queue again'), default => __('Recompute') } }}</x-secondary-button>
                </form>
            @endif
            @if ($status === PayrollRunStatus::Computed)
                <form method="POST" action="{{ route('payroll.runs.approve', $run) }}">
                    @csrf
                    <x-primary-button :disabled="$hasBlockingWarnings">{{ __('Approve') }}</x-primary-button>
                </form>
            @endif
            @if ($status === PayrollRunStatus::Approved)
                <form method="POST" action="{{ route('payroll.runs.release', $run) }}" data-confirm="{{ __('Release this payroll? Payslips become available to employees and the payroll is locked.') }}">
                    @csrf
                    <x-primary-button>{{ __('Release payslips') }}</x-primary-button>
                </form>
                <form method="POST" action="{{ route('payroll.runs.reopen', $run) }}">
                    @csrf
                    <x-secondary-button type="submit">{{ __('Reopen for corrections') }}</x-secondary-button>
                </form>
            @endif

            @if (in_array($status, [PayrollRunStatus::Computed, PayrollRunStatus::Approved, PayrollRunStatus::Released], true))
                <span class="text-sm text-gray-500">{{ __('Register:') }}</span>
                @foreach (['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF'] as $format => $label)
                    <a href="{{ route('payroll.runs.register', [$run, $format]) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900" @if ($format === 'pdf') target="_blank" rel="noopener" @endif>{{ $label }}</a>
                @endforeach
            @endif

            @if ($status->canDelete())
                <form method="POST" action="{{ route('payroll.runs.destroy', $run) }}" class="ml-auto" data-confirm="{{ __('Delete this payroll?') }}">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>{{ __('Delete') }}</x-danger-button>
                </form>
            @endif
        </div>

        @if ($status === PayrollRunStatus::Released)
            <p class="mt-3 text-sm text-gray-500">{{ __('Released payrolls are locked. Corrections go in a later payroll.') }}</p>
        @endif
    </x-card>

    @if ($run->warnings)
        <x-card :title="__('Needs attention')" :description="$hasBlockingWarnings ? __('Fix the items marked “Must fix”, then recompute. The payroll cannot be approved until they are resolved.') : __('For information; these do not stop approval.')">
            <ul class="space-y-2 text-sm">
                @foreach ($run->warnings as $warning)
                    <li class="flex items-start gap-2">
                        @if ($warning['blocking'])
                            <x-badge color="red">{{ __('Must fix') }}</x-badge>
                        @else
                            <x-badge color="yellow">{{ __('Note') }}</x-badge>
                        @endif
                        <span class="text-gray-700">{{ $warning['message'] }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card :title="__('Employees')">
        <x-data-table :empty="__('Nobody is in this payroll yet. Compute it to see the employees.')">
            <thead>
                <tr>
                    <th>{{ __('Employee') }}</th>
                    <th>{{ __('Office') }}</th>
                    <th>{{ __('Rate') }}</th>
                    <th class="text-right">{{ __('Gross') }}</th>
                    <th class="text-right">{{ __('Deductions') }}</th>
                    <th class="text-right">{{ __('Net pay') }}</th>
                    <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td class="font-medium text-gray-900">
                            {{ $item->employee->sortable_name }}
                            <span class="block text-xs font-normal text-gray-500">{{ $item->position_title }}</span>
                            @if ($item->notes)
                                <span class="block text-xs font-normal text-yellow-700">{{ implode(' ', $item->notes) }}</span>
                            @endif
                        </td>
                        <td>{{ $item->department_name }}</td>
                        <td class="text-sm">{{ $register->rateLabel($item) }}</td>
                        <td class="text-right tabular-nums">{{ Money::format($item->gross_pay) }}</td>
                        <td class="text-right tabular-nums">{{ Money::format($item->total_deductions) }}</td>
                        <td class="text-right font-medium tabular-nums">{{ Money::format($item->net_pay) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('payroll.payslips.show', $item) }}" target="_blank" rel="noopener" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Payslip') }}</a>
                            <a href="{{ route('payroll.employees.show', $item->employee) }}" class="ml-3 text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Pay setup') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
