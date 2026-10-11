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
            <x-stat-card :label="__('Vacation leave')" :value="LeaveDays::format($myLeave['vacation']['balance'])" icon="calendar" />
            <x-stat-card :label="__('Sick leave')" :value="LeaveDays::format($myLeave['sick']['balance'])" icon="calendar" />
            <x-stat-card :label="__('Applications in progress')" :value="$myOpenLeaveRequests" icon="inbox" />
        </div>
    </x-card>
@endif
