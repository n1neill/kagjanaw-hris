@use('App\Enums\LeaveLedgerEntryType')
@use('App\Services\Leave\LeaveDays')

<x-app-layout :title="__('Leave Card')">
    <x-slot name="header">{{ __('Leave Card') }}</x-slot>

    <x-card :title="$employee->full_name" :description="$employee->employee_number.' · '.$employee->position?->title.' · '.$employee->department?->name">
        @if ($canManage)
            <x-slot name="actions">
                <x-button-link :href="route('leave.credits')" variant="secondary">{{ __('All balances') }}</x-button-link>
            </x-slot>
        @endif

        @unless ($employee->earnsLeaveCredits())
            <p class="mb-4 rounded-md bg-yellow-50 px-3 py-2 text-sm text-yellow-800">{{ __(':type employees do not earn vacation or sick leave credits.', ['type' => $employee->employmentType?->name]) }}</p>
        @endunless

        @include('leave.partials.balances')
    </x-card>

    <x-card :title="__('Vacation and sick leave ledger')" :description="__('Every credit earned, used, deducted or adjusted, with the balance after each entry.')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th rowspan="2" class="border-b border-gray-200 py-2 pr-3 text-left align-bottom">{{ __('Date') }}</th>
                        <th rowspan="2" class="border-b border-gray-200 px-2 py-2 text-left align-bottom">{{ __('Particulars') }}</th>
                        <th colspan="4" class="border-b border-l border-gray-200 px-2 py-1 text-center">{{ __('Vacation Leave') }}</th>
                        <th colspan="4" class="border-b border-l border-gray-200 px-2 py-1 text-center">{{ __('Sick Leave') }}</th>
                        <th rowspan="2" class="border-b border-l border-gray-200 py-2 pl-2 text-left align-bottom">{{ __('Remarks') }}</th>
                    </tr>
                    <tr class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500">
                        @foreach ([1, 2] as $ledger)
                            <th class="border-b border-l border-gray-200 px-2 py-1 text-right">{{ __('Earned') }}</th>
                            <th class="border-b border-gray-200 px-2 py-1 text-right">{{ __('Used') }}</th>
                            <th class="border-b border-gray-200 px-2 py-1 text-right">{{ __('Balance') }}</th>
                            <th class="border-b border-gray-200 px-2 py-1 text-right">{{ __('W/o pay') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        @php
                            $days = LeaveDays::toThousandths($entry->days);
                            $isOpening = $entry->entry_type === LeaveLedgerEntryType::OpeningBalance;
                            $cells = [
                                'earned' => ! $isOpening && $days > 0 ? LeaveDays::format($days) : '',
                                'used' => $days < 0 ? LeaveDays::format(-$days) : '',
                                'balance' => $entry->balance_after,
                                'withoutPay' => $entry->days_without_pay !== '0.000' ? $entry->days_without_pay : '',
                            ];
                            $isVacation = $entry->leave_type_id === $vacationTypeId;
                        @endphp
                        <tr class="border-b border-gray-100">
                            <td class="whitespace-nowrap py-1.5 pr-3">{{ $entry->entry_date->format('M j, Y') }}</td>
                            <td class="px-2 py-1.5">
                                @if ($entry->leave_request_id)
                                    <a href="{{ route('leave.requests.show', $entry->leave_request_id) }}" class="text-brand-700 hover:underline">{{ $entry->particulars }}</a>
                                @else
                                    {{ $entry->particulars }}
                                @endif
                            </td>
                            @foreach ([true, false] as $vacationColumns)
                                @foreach ($cells as $key => $value)
                                    <td @class(['px-2 py-1.5 text-right tabular-nums', 'border-l border-gray-100' => $key === 'earned', 'font-medium text-gray-900' => $key === 'balance'])>
                                        {{ $isVacation === $vacationColumns ? $value : '' }}
                                    </td>
                                @endforeach
                            @endforeach
                            <td class="border-l border-gray-100 py-1.5 pl-2 text-xs text-gray-600">
                                {{ $entry->remarks }}
                                @if ($entry->creator)
                                    <span class="block text-gray-400">{{ $entry->creator->name }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-6 text-center text-gray-500">{{ __('No entries yet. HR imports the opening balance; credits are then posted each month.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    @if ($canManage)
        <div class="grid gap-6 lg:grid-cols-2">
            <x-card :title="__('Adjust a balance')" :description="__('For corrections only. Enter a negative number to deduct. The reason is recorded on the leave card.')">
                <form method="POST" action="{{ route('leave.adjustments.store', $employee) }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="ledger" :value="__('Leave')" />
                            <x-select-input id="ledger" name="ledger" class="mt-1" :options="['VL' => __('Vacation Leave'), 'SL' => __('Sick Leave')]" :selected="old('ledger', 'VL')" />
                            <x-input-error :messages="$errors->get('ledger')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="days" :value="__('Days (+/−)')" />
                            <x-text-input id="days" name="days" inputmode="decimal" class="mt-1" placeholder="1.250" :value="old('days')" required />
                            <x-input-error :messages="$errors->get('days')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="entry_date" :value="__('Date')" />
                            <x-text-input id="entry_date" name="entry_date" type="date" class="mt-1" :value="old('entry_date', now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('entry_date')" class="mt-2" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="reason" :value="__('Reason')" />
                        <x-textarea-input id="reason" name="reason" rows="2" class="mt-1" required>{{ old('reason') }}</x-textarea-input>
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>
                    <x-primary-button>{{ __('Post Adjustment') }}</x-primary-button>
                </form>
            </x-card>

            <x-card :title="__('Certificate of Leave Credits')" :description="__('Issued by the HRMO. Applications still pending are subtracted from the balance.')">
                <form method="GET" action="{{ route('leave.certificate', $employee) }}" target="_blank" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="as_of" :value="__('As of')" />
                            <x-text-input id="as_of" name="as_of" type="date" class="mt-1" :value="now()->toDateString()" max="{{ now()->toDateString() }}" />
                        </div>
                        <div>
                            <x-input-label for="purpose" :value="__('Purpose (optional)')" />
                            <x-text-input id="purpose" name="purpose" class="mt-1" placeholder="{{ __('e.g. loan application') }}" />
                        </div>
                    </div>
                    <x-secondary-button type="submit">
                        <x-icon name="document" class="size-4" />
                        {{ __('Generate PDF') }}
                    </x-secondary-button>
                </form>
            </x-card>
        </div>
    @endif
</x-app-layout>
