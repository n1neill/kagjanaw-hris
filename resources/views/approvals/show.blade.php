<x-app-layout :title="__('Submitted Change')">
    <x-slot name="header">{{ __('Submitted Change') }}</x-slot>

    @if ($errors->any())
        <x-alert type="error" class="mb-6">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="$change->description" :description="__($change->action->label())">
                <x-slot name="actions">
                    <x-badge :color="$change->status->badgeColor()">{{ __($change->status->label()) }}</x-badge>
                </x-slot>

                <dl class="grid gap-4 sm:grid-cols-3">
                    <x-detail :label="__('Submitted by')">{{ $change->requester->name }}</x-detail>
                    <x-detail :label="__('Submitted')">{{ $change->created_at->format('M j, Y g:i A') }}</x-detail>
                    <x-detail :label="__('Reviewed')">
                        @if ($change->reviewed_at)
                            {{ $change->reviewer?->name ?? __('unknown user') }} · {{ $change->reviewed_at->format('M j, Y g:i A') }}
                        @endif
                    </x-detail>
                    @if ($change->review_remarks)
                        <x-detail :label="__('Remarks')" class="sm:col-span-3">“{{ $change->review_remarks }}”</x-detail>
                    @endif
                </dl>

                @if ($change->failure_message && $change->isPending())
                    <x-alert type="warning" class="mt-5">
                        {{ __('The last attempt to approve this change failed: :reason', ['reason' => $change->failure_message]) }}
                    </x-alert>
                @endif
            </x-card>

            <x-card :title="__('Details')" :description="$change->action->isUpdate() && $change->subject ? __('Fields that differ from the current record are highlighted.') : null">
                @if ($rows === [] && empty($change->files))
                    <p class="text-sm text-ink-muted">{{ __('This change has no further details.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-line text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                    <th class="py-2 pr-3">{{ __('Field') }}</th>
                                    @if ($change->action->isUpdate() && $change->subject)
                                        <th class="px-2 py-2">{{ __('Current') }}</th>
                                    @endif
                                    <th class="py-2 pl-2">{{ $change->action->isUpdate() ? __('Proposed') : __('Value') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr @class(['border-b border-line last:border-0 align-top', 'bg-yellow-50 dark:bg-yellow-500/10' => $row['changed']])>
                                        <td class="py-1.5 pr-3 font-medium text-ink">{{ $row['label'] }}</td>
                                        @if ($change->action->isUpdate() && $change->subject)
                                            <td class="px-2 py-1.5 text-ink-muted"><span class="whitespace-pre-wrap break-words">{{ $row['current'] ?? '—' }}</span></td>
                                        @endif
                                        <td class="py-1.5 pl-2 text-ink"><span class="whitespace-pre-wrap break-words">{{ $row['proposed'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if (! empty($change->files) && $change->isPending())
                        <h3 class="mt-6 text-sm font-semibold text-ink">{{ __('Submitted files') }}</h3>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($change->files as $key => $file)
                                <li>
                                    <a href="{{ route('approvals.file', [$change, $key]) }}" class="inline-flex items-center gap-1 font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">
                                        <x-icon name="arrow-down-tray" class="size-4" />
                                        {{ $file['name'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            @can('review', $change)
                <x-card :title="__('Review this change')">
                    <form method="POST" action="{{ route('approvals.approve', $change) }}" class="space-y-3">
                        @csrf
                        <x-input-label for="approve_remarks" :value="__('Remarks (optional)')" />
                        <x-textarea-input id="approve_remarks" name="remarks" rows="2"></x-textarea-input>
                        <x-primary-button class="w-full">{{ __('Approve and record') }}</x-primary-button>
                        <p class="text-xs text-ink-muted">{{ __('The change is applied to the records as submitted.') }}</p>
                    </form>
                    <form method="POST" action="{{ route('approvals.reject', $change) }}" class="mt-4 space-y-3 border-t border-line pt-4">
                        @csrf
                        <x-input-label for="reject_remarks" :value="__('Reason for rejecting')" />
                        <x-textarea-input id="reject_remarks" name="remarks" rows="2" required></x-textarea-input>
                        <x-danger-button class="w-full">{{ __('Reject') }}</x-danger-button>
                    </form>
                </x-card>
            @endcan

            @can('cancel', $change)
                <x-card :title="__('Withdraw')">
                    <form method="POST" action="{{ route('approvals.cancel', $change) }}" data-confirm="{{ __('Withdraw this change?') }}" class="space-y-3">
                        @csrf
                        <p class="text-sm text-ink-muted">{{ __('The change has not been recorded. Withdraw it if it is no longer needed.') }}</p>
                        <x-secondary-button type="submit" class="w-full justify-center">{{ __('Withdraw change') }}</x-secondary-button>
                    </form>
                </x-card>
            @endcan

            <x-button-link :href="route('approvals.index')" variant="secondary">{{ __('Back to approvals') }}</x-button-link>
        </div>
    </div>
</x-app-layout>
