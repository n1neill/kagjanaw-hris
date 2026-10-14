<x-app-layout :title="__('Monthly Leave Posting')">
    <x-slot name="header">{{ __('Monthly Leave Posting') }}</x-slot>

    <x-card :title="__('Leave credits for :month', ['month' => $month->format('F Y')])"
            :description="__('Each employee earns 1.250 days of vacation and of sick leave a month (less for partial months and days without pay), and the month\'s tardiness and undertime is deducted from vacation leave. Credits are posted once the DTR for the month is validated; validating a DTR posts it automatically.')">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <form method="GET" action="{{ route('leave.posting.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="month" :value="__('Month')" />
                    <x-text-input id="month" name="month" type="month" class="mt-1" :value="$month->format('Y-m')" max="{{ now()->subMonthNoOverflow()->format('Y-m') }}" />
                </div>
                <x-secondary-button type="submit">{{ __('Show') }}</x-secondary-button>
            </form>

            <form method="POST" action="{{ route('leave.posting.store', ['month' => $month->format('Y-m')]) }}">
                @csrf
                <x-primary-button>{{ __('Post for all ready employees') }}</x-primary-button>
            </form>
        </div>

        @php($ready = $rows->filter(fn ($row) => ! $row['posted'] && $row['reason'] === null)->count())
        <dl class="mb-6 grid gap-4 sm:grid-cols-3">
            <x-stat-card :label="__('Posted')" :value="$rows->where('posted', true)->count()" />
            <x-stat-card :label="__('Ready to post')" :value="$ready" />
            <x-stat-card :label="__('Waiting')" :value="$rows->count() - $rows->where('posted', true)->count() - $ready" :hint="__('Usually a DTR not yet validated')" />
        </dl>

        <x-data-table :empty="__('No employees earn leave credits for this month.')">
            <thead>
                <tr>
                    <th>{{ __('Employee') }}</th>
                    <th>{{ __('Office') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $row['employee']->sortable_name }}</td>
                        <td>{{ $row['employee']->department?->name }}</td>
                        <td>
                            @if ($row['posted'])
                                <x-badge color="green">{{ __('Posted') }}</x-badge>
                            @elseif ($row['reason'] === null)
                                <x-badge color="blue">{{ __('Ready') }}</x-badge>
                            @else
                                <x-badge color="yellow">{{ __('Waiting') }}</x-badge>
                                <span class="ml-1 text-xs text-gray-600">{{ $row['reason'] }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('attendance.dtr.show', [$row['employee'], 'month' => $month->format('Y-m')]) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('DTR') }}</a>
                            <a href="{{ route('leave.card', $row['employee']) }}" class="ml-3 text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Leave card') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
