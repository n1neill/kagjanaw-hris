{{--
    A list of submitted changes.
    Expects: $changes, $showRequester (bool), $showReviewer (bool), $empty (string)
--}}
<x-data-table :empty="$empty">
    <thead>
        <tr>
            <th>{{ __('Change') }}</th>
            @if ($showRequester)
                <th>{{ __('Submitted by') }}</th>
            @endif
            <th>{{ __('Submitted') }}</th>
            <th>{{ __('Status') }}</th>
            @if ($showReviewer)
                <th>{{ __('Reviewed by') }}</th>
            @endif
            <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($changes as $change)
            <tr>
                <td>
                    <span class="font-medium text-ink">{{ $change->description }}</span>
                    @if ($change->failure_message && $change->isPending())
                        <span class="block text-xs text-red-700 dark:text-red-300">{{ __('Could not be applied; see details.') }}</span>
                    @endif
                </td>
                @if ($showRequester)
                    <td>{{ $change->requester->name }}</td>
                @endif
                <td data-order="{{ $change->created_at->timestamp }}" class="whitespace-nowrap">{{ $change->created_at->format('M j, Y g:i A') }}</td>
                <td><x-badge :color="$change->status->badgeColor()">{{ __($change->status->label()) }}</x-badge></td>
                @if ($showReviewer)
                    <td>{{ $change->reviewer?->name ?? '—' }}</td>
                @endif
                <td class="text-right">
                    <x-action-icon icon="eye" :label="__('View')" href="{{ route('approvals.show', $change) }}" />
                </td>
            </tr>
        @endforeach
    </tbody>
</x-data-table>
