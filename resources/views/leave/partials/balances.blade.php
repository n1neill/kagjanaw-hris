{{--
    Vacation and sick leave balances plus yearly allotments.
    Expects: $summary (LeaveBalanceService::summary)
--}}
@use('App\Services\Leave\LeaveDays')

<div class="grid gap-4 sm:grid-cols-2">
    @foreach (['vacation' => __('Vacation Leave'), 'sick' => __('Sick Leave')] as $key => $label)
        @php($balance = $summary[$key])
        <x-stat-card
            :label="$label"
            :value="LeaveDays::format($balance['balance'])"
            icon="calendar"
            :hint="$balance['pending'] > 0
                ? __(':pending days applied for · :available available', ['pending' => LeaveDays::format($balance['pending']), 'available' => LeaveDays::format($balance['available'])])
                : __('days available')"
            :progress="$balance['balance'] > 0 ? min($balance['pending'] / $balance['balance'] * 100, 100) : null"
        />
    @endforeach
</div>

@if ($summary['allotments']->isNotEmpty())
    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-line bg-surface-muted/50 text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                    <th class="px-3 py-2.5">{{ __('Yearly allotment') }}</th>
                    <th class="px-2 py-2 text-right">{{ __('Allowed') }}</th>
                    <th class="px-2 py-2 text-right">{{ __('Used or applied') }}</th>
                    <th class="py-2 pl-2 text-right">{{ __('Remaining') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['allotments'] as $allotment)
                    <tr class="border-b border-line transition-colors last:border-0 hover:bg-surface-muted/60">
                        <td class="px-3 py-2.5 text-ink">{{ $allotment['type']->name }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ LeaveDays::format($allotment['allowance']) }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">
                            {{ LeaveDays::format($allotment['used']) }}
                            <x-meter :value="$allotment['used']" :max="$allotment['allowance']" :color="$allotment['allowance'] > 0 && $allotment['used'] >= $allotment['allowance'] ? 'red' : 'brand'" :label="$allotment['type']->name" class="ml-auto mt-1.5 w-24" />
                        </td>
                        <td class="py-2 pl-2 text-right font-medium tabular-nums">{{ LeaveDays::format($allotment['remaining']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
