{{--
    Vacation and sick leave balances plus yearly allotments.
    Expects: $summary (LeaveBalanceService::summary)
--}}
@use('App\Services\Leave\LeaveDays')

<div class="grid gap-4 sm:grid-cols-2">
    @foreach (['vacation' => __('Vacation Leave'), 'sick' => __('Sick Leave')] as $key => $label)
        @php($balance = $summary[$key])
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-xs">
            <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ LeaveDays::format($balance['balance']) }}</p>
            <p class="mt-1 text-xs text-gray-500">
                @if ($balance['pending'] > 0)
                    {{ __(':pending days applied for · :available available', ['pending' => LeaveDays::format($balance['pending']), 'available' => LeaveDays::format($balance['available'])]) }}
                @else
                    {{ __('days available') }}
                @endif
            </p>
        </div>
    @endforeach
</div>

@if ($summary['allotments']->isNotEmpty())
    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="py-2 pr-3">{{ __('Yearly allotment') }}</th>
                    <th class="px-2 py-2 text-right">{{ __('Allowed') }}</th>
                    <th class="px-2 py-2 text-right">{{ __('Used or applied') }}</th>
                    <th class="py-2 pl-2 text-right">{{ __('Remaining') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['allotments'] as $allotment)
                    <tr class="border-b border-gray-100 last:border-0">
                        <td class="py-2 pr-3 text-gray-900">{{ $allotment['type']->name }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ LeaveDays::format($allotment['allowance']) }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ LeaveDays::format($allotment['used']) }}</td>
                        <td class="py-2 pl-2 text-right font-medium tabular-nums">{{ LeaveDays::format($allotment['remaining']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
