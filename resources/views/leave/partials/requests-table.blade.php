{{--
    A list of leave applications.
    Expects: $requests, $showEmployee (bool), $empty (string)
--}}
<x-data-table :empty="$empty">
    <thead>
        <tr>
            @if ($showEmployee)
                <th>{{ __('Employee') }}</th>
            @endif
            <th>{{ __('Leave') }}</th>
            <th>{{ __('Dates') }}</th>
            <th class="text-right">{{ __('Days') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Filed') }}</th>
            <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($requests as $leaveRequest)
            <tr>
                @if ($showEmployee)
                    <td>
                        <span class="font-medium text-gray-900">{{ $leaveRequest->employee->sortable_name }}</span>
                        <span class="block text-xs text-gray-500">{{ $leaveRequest->employee->department?->name }}</span>
                    </td>
                @endif
                <td>{{ $leaveRequest->leaveType->name }}</td>
                <td data-order="{{ $leaveRequest->date_from->format('Y-m-d') }}" class="whitespace-nowrap">{{ $leaveRequest->periodLabel() }}</td>
                <td class="text-right tabular-nums">{{ $leaveRequest->days }}</td>
                <td><x-badge :color="$leaveRequest->status->badgeColor()">{{ $leaveRequest->status->label() }}</x-badge></td>
                <td data-order="{{ $leaveRequest->created_at->timestamp }}" class="whitespace-nowrap">{{ $leaveRequest->created_at->format('M j, Y') }}</td>
                <td class="text-right">
                    <a href="{{ route('leave.requests.show', $leaveRequest) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('View') }}</a>
                </td>
            </tr>
        @endforeach
    </tbody>
</x-data-table>
