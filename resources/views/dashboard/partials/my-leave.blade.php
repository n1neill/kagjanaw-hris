{{--
    The signed-in employee's leave balances on their dashboard.
    Expects: $myLeave (LeaveBalanceService::summary or null), $myOpenLeaveRequests (int)
--}}
@use('App\Services\Leave\LeaveDays')

@if ($myLeave)
    <x-card :title="__('My Leave')">
        <x-slot name="actions">
            <x-button-link :href="route('leave.my')" variant="secondary">{{ __('My Leave') }}</x-button-link>
            <x-button-link :href="route('leave.requests.create')">{{ __('Apply for Leave') }}</x-button-link>
        </x-slot>

        <div class="grid gap-4 sm:grid-cols-3">
            @foreach (['vacation' => __('Vacation leave'), 'sick' => __('Sick leave')] as $type => $typeLabel)
                @php
                    $balance = $myLeave[$type]['balance'];
                    $pending = $myLeave[$type]['pending'];
                @endphp
                <x-stat-card
                    :label="$typeLabel"
                    :value="LeaveDays::format($balance)"
                    icon="calendar"
                    :hint="$pending > 0 ? __(':days days reserved by pending applications', ['days' => LeaveDays::format($pending)]) : __('Days available')"
                    :progress="$balance > 0 ? min($pending / $balance * 100, 100) : null"
                />
            @endforeach
            <x-stat-card :label="__('Applications in progress')" :value="$myOpenLeaveRequests" icon="inbox" />
        </div>
    </x-card>
@endif
