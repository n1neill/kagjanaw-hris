@use('App\Enums\LeaveRequestStatus')
@use('App\Services\Leave\LeaveDays')

@php
    $employee = $leaveRequest->employee;
    $status = $leaveRequest->status;
@endphp

<x-app-layout :title="__('Leave Application')">
    <x-slot name="header">{{ __('Leave Application') }}</x-slot>

    @if ($errors->any())
        <div role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="$leaveRequest->leaveType->name" :description="$isOwn ? __('Your application') : $employee->full_name.' · '.$employee->position?->title.' · '.$employee->department?->name">
                <x-slot name="actions">
                    <x-badge :color="$status->badgeColor()">{{ $status->label() }}</x-badge>
                </x-slot>

                <dl class="grid gap-4 sm:grid-cols-3">
                    <x-detail :label="__('Dates')">{{ $leaveRequest->periodLabel() }}</x-detail>
                    <x-detail :label="__('Working days applied for')">{{ $leaveRequest->days }}</x-detail>
                    <x-detail :label="__('Filed')">{{ $leaveRequest->created_at->format('M j, Y g:i A') }}</x-detail>
                    @if ($status === LeaveRequestStatus::Approved)
                        <x-detail :label="__('With pay')">{{ $leaveRequest->days_with_pay }}</x-detail>
                        <x-detail :label="__('Without pay')">{{ $leaveRequest->days_without_pay }}</x-detail>
                    @endif
                    <x-detail :label="__('Details')" class="sm:col-span-3">{{ $leaveRequest->details }}</x-detail>
                    @if ($leaveRequest->hasAttachment())
                        <x-detail :label="__('Supporting document')" class="sm:col-span-3">
                            <a href="{{ route('leave.requests.attachment', $leaveRequest) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:text-brand-900">
                                <x-icon name="document" class="size-4" />
                                {{ $leaveRequest->attachment_name }}
                            </a>
                        </x-detail>
                    @endif
                </dl>
            </x-card>

            <x-card :title="__('Progress')">
                <ol class="space-y-4 text-sm">
                    <li class="flex gap-3">
                        <x-icon name="check" class="size-5 shrink-0 text-green-600" />
                        <div>
                            <p class="font-medium text-gray-900">{{ __('Filed') }}</p>
                            <p class="text-gray-500">{{ $leaveRequest->filer?->name ?? $employee->full_name }} · {{ $leaveRequest->created_at->format('M j, Y g:i A') }}</p>
                        </div>
                    </li>

                    <li class="flex gap-3">
                        @if ($leaveRequest->endorsed_at)
                            <x-icon name="check" class="size-5 shrink-0 {{ $status === LeaveRequestStatus::Rejected && ! $leaveRequest->decided_at ? 'text-red-600' : 'text-green-600' }}" />
                        @else
                            <x-icon name="clock" class="size-5 shrink-0 text-gray-400" />
                        @endif
                        <div>
                            <p class="font-medium text-gray-900">
                                {{ __('Endorsement by the :who', ['who' => $leaveRequest->endorsement_route->label()]) }}
                            </p>
                            @if ($leaveRequest->endorsement_route === \App\Enums\LeaveEndorsementRoute::NotRequired)
                                <p class="text-gray-500">{{ __('Not required; sent straight to HR.') }}</p>
                            @elseif ($leaveRequest->endorsed_at)
                                <p class="text-gray-500">
                                    {{ $status === LeaveRequestStatus::Rejected && ! $leaveRequest->decided_at ? __('Rejected') : __('Endorsed') }}
                                    {{ __('by :name', ['name' => $leaveRequest->endorser?->name ?? __('unknown user')]) }} · {{ $leaveRequest->endorsed_at->format('M j, Y g:i A') }}
                                </p>
                                @if ($leaveRequest->endorsement_remarks)
                                    <p class="mt-1 text-gray-700">“{{ $leaveRequest->endorsement_remarks }}”</p>
                                @endif
                            @else
                                <p class="text-gray-500">{{ __('Waiting') }}</p>
                            @endif
                        </div>
                    </li>

                    <li class="flex gap-3">
                        @if ($leaveRequest->decided_at)
                            <x-icon name="check" class="size-5 shrink-0 {{ $status === LeaveRequestStatus::Approved ? 'text-green-600' : 'text-red-600' }}" />
                        @else
                            <x-icon name="clock" class="size-5 shrink-0 text-gray-400" />
                        @endif
                        <div>
                            <p class="font-medium text-gray-900">{{ __('Approval by HR') }}</p>
                            @if ($leaveRequest->decided_at)
                                <p class="text-gray-500">
                                    {{ $leaveRequest->decided_at && $leaveRequest->days_with_pay !== null ? __('Approved') : __('Rejected') }}
                                    {{ __('by :name', ['name' => $leaveRequest->decider?->name ?? __('unknown user')]) }} · {{ $leaveRequest->decided_at->format('M j, Y g:i A') }}
                                </p>
                                @if ($leaveRequest->decision_remarks)
                                    <p class="mt-1 text-gray-700">“{{ $leaveRequest->decision_remarks }}”</p>
                                @endif
                            @else
                                <p class="text-gray-500">{{ $status->isOpen() ? __('Waiting') : '—' }}</p>
                            @endif
                        </div>
                    </li>

                    @if ($leaveRequest->cancelled_at)
                        <li class="flex gap-3">
                            <x-icon name="x" class="size-5 shrink-0 text-gray-500" />
                            <div>
                                <p class="font-medium text-gray-900">{{ __('Cancelled') }}</p>
                                <p class="text-gray-500">{{ __('by :name', ['name' => $leaveRequest->canceller?->name ?? __('unknown user')]) }} · {{ $leaveRequest->cancelled_at->format('M j, Y g:i A') }}</p>
                                @if ($leaveRequest->cancellation_remarks)
                                    <p class="mt-1 text-gray-700">“{{ $leaveRequest->cancellation_remarks }}”</p>
                                @endif
                            </div>
                        </li>
                    @endif
                </ol>
            </x-card>

            <x-card :title="__('Days covered')">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="py-2 pr-3">{{ __('Date') }}</th>
                                <th class="px-2 py-2">{{ __('Portion') }}</th>
                                <th class="px-2 py-2 text-right">{{ __('Days') }}</th>
                                @if ($status === LeaveRequestStatus::Approved)
                                    <th class="py-2 pl-2 text-right">{{ __('With pay') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($leaveRequest->leaveDays as $day)
                                <tr class="border-b border-gray-100 last:border-0">
                                    <td class="py-1.5 pr-3">{{ $day->date->format('D, M j, Y') }}</td>
                                    <td class="px-2 py-1.5">{{ $day->portion->label() }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ $day->days }}</td>
                                    @if ($status === LeaveRequestStatus::Approved)
                                        <td class="py-1.5 pl-2 text-right tabular-nums">{{ $day->days_with_pay }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($leaveRequest->ledgerEntries->isNotEmpty())
                    <h3 class="mt-6 text-sm font-semibold text-gray-900">{{ __('Leave card entries') }}</h3>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700">
                        @foreach ($leaveRequest->ledgerEntries as $entry)
                            <li>
                                {{ $entry->leaveType->name }}: {{ $entry->entry_type->label() }}
                                <span class="tabular-nums">{{ $entry->days }}</span>
                                @if ($entry->days_without_pay !== '0.000')
                                    · {{ __(':days without pay', ['days' => $entry->days_without_pay]) }}
                                @endif
                                · {{ __('balance :balance', ['balance' => $entry->balance_after]) }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            @if ($can['endorse'])
                <x-card :title="__('Endorse this application')">
                    <form method="POST" action="{{ route('leave.requests.endorse', $leaveRequest) }}" class="space-y-3">
                        @csrf
                        <x-input-label for="endorse_remarks" :value="__('Remarks (optional)')" />
                        <x-textarea-input id="endorse_remarks" name="remarks" rows="2">{{ old('remarks') }}</x-textarea-input>
                        <x-primary-button class="w-full">{{ __('Endorse to HR') }}</x-primary-button>
                    </form>
                    <form method="POST" action="{{ route('leave.requests.reject', $leaveRequest) }}" class="mt-4 space-y-3 border-t border-gray-200 pt-4">
                        @csrf
                        <x-input-label for="endorse_reject_remarks" :value="__('Reason for rejecting')" />
                        <x-textarea-input id="endorse_reject_remarks" name="remarks" rows="2" required></x-textarea-input>
                        <x-danger-button class="w-full">{{ __('Reject') }}</x-danger-button>
                    </form>
                </x-card>
            @endif

            @if ($can['decide'])
                <x-card :title="__('Decide this application')">
                    <form method="POST" action="{{ route('leave.requests.approve', $leaveRequest) }}" class="space-y-3">
                        @csrf
                        <x-input-label for="approve_remarks" :value="__('Remarks (optional)')" />
                        <x-textarea-input id="approve_remarks" name="remarks" rows="2"></x-textarea-input>
                        <x-primary-button class="w-full">{{ __('Approve') }}</x-primary-button>
                        <p class="text-xs text-gray-500">{{ __('Credits are charged now. Days beyond the employee\'s credits are leave without pay.') }}</p>
                    </form>
                    <form method="POST" action="{{ route('leave.requests.reject', $leaveRequest) }}" class="mt-4 space-y-3 border-t border-gray-200 pt-4">
                        @csrf
                        <x-input-label for="decide_reject_remarks" :value="__('Reason for rejecting')" />
                        <x-textarea-input id="decide_reject_remarks" name="remarks" rows="2" required></x-textarea-input>
                        <x-danger-button class="w-full">{{ __('Reject') }}</x-danger-button>
                    </form>
                </x-card>
            @endif

            @if ($can['cancel'])
                <x-card :title="$status === LeaveRequestStatus::Approved ? __('Cancel approved leave') : __('Withdraw application')">
                    <form method="POST" action="{{ route('leave.requests.cancel', $leaveRequest) }}" class="space-y-3"
                          onsubmit="return confirm(@js(__('Cancel this leave application?')))">
                        @csrf
                        <x-input-label for="cancel_remarks" :value="$status === LeaveRequestStatus::Approved ? __('Reason (e.g. recalled to work)') : __('Reason (optional)')" />
                        <x-textarea-input id="cancel_remarks" name="remarks" rows="2" :required="$status === LeaveRequestStatus::Approved"></x-textarea-input>
                        @if ($status === LeaveRequestStatus::Approved)
                            <p class="text-xs text-gray-500">{{ __('The credits used are restored and the days return to the DTR.') }}</p>
                        @endif
                        <x-secondary-button type="submit" class="w-full justify-center">{{ __('Cancel Leave') }}</x-secondary-button>
                    </form>
                </x-card>
            @endif

            <x-card :title="$isOwn ? __('Your balances') : __('Employee\'s balances')">
                @if ($can['viewLeaveCard'])
                    <x-slot name="actions">
                        <a href="{{ route('leave.card', $employee) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Leave card') }}</a>
                    </x-slot>
                @endif
                @include('leave.partials.balances')
            </x-card>
        </div>
    </div>
</x-app-layout>
