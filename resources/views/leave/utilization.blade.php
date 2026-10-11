@use('App\Services\Leave\LeaveDays')

<x-app-layout :title="__('Leave Utilization')">
    <x-slot name="header">{{ __('Leave Utilization') }}</x-slot>

    <x-card :title="__('Approved leave taken, :from to :to', ['from' => $from->format('M j, Y'), 'to' => $to->format('M j, Y')])">
        <x-slot name="actions">
            <x-button-link :href="route('leave.reports.utilization.export', array_filter(['year' => $year, 'month' => $month, 'department' => $departmentId]))" variant="secondary">{{ __('Export CSV') }}</x-button-link>
        </x-slot>

        <form method="GET" action="{{ route('leave.reports.utilization') }}" class="mb-6 flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="year" :value="__('Year')" />
                <x-text-input id="year" name="year" type="number" min="2000" max="2100" class="mt-1 w-28" :value="$year" />
            </div>
            <div>
                <x-input-label for="month" :value="__('Month')" />
                <x-select-input id="month" name="month" class="mt-1" :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\CarbonImmutable::create(2000, $m)->format('F')])->all()" :selected="$month" :placeholder="__('Whole year')" />
            </div>
            <div>
                <x-input-label for="department" :value="__('Office')" />
                <x-select-input id="department" name="department" class="mt-1" :options="$departments" :selected="$departmentId" :placeholder="__('All offices')" />
            </div>
            <x-secondary-button type="submit">{{ __('Show') }}</x-secondary-button>
        </form>

        <dl class="mb-6 grid gap-4 sm:grid-cols-4">
            <x-stat-card :label="__('Employees on leave')" :value="$totals['employees']" />
            <x-stat-card :label="__('Days taken')" :value="LeaveDays::format($totals['days'])" />
            <x-stat-card :label="__('With pay')" :value="LeaveDays::format($totals['with_pay'])" />
            <x-stat-card :label="__('Without pay')" :value="LeaveDays::format($totals['without_pay'])" />
        </dl>

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ([['title' => __('By leave type'), 'rows' => $byType, 'label' => fn ($row) => $row['type']->name], ['title' => __('By office'), 'rows' => $byDepartment, 'label' => fn ($row) => $row['department']]] as $section)
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ $section['title'] }}</h3>
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <th class="py-2 pr-3"></th>
                                    <th class="px-2 py-2 text-right">{{ __('Employees') }}</th>
                                    <th class="px-2 py-2 text-right">{{ __('Days') }}</th>
                                    <th class="px-2 py-2 text-right">{{ __('With pay') }}</th>
                                    <th class="py-2 pl-2 text-right">{{ __('Without pay') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($section['rows'] as $row)
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <td class="py-1.5 pr-3 text-gray-900">{{ $section['label']($row) }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums">{{ $row['employees'] }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums">{{ LeaveDays::format($row['days']) }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums">{{ LeaveDays::format($row['with_pay']) }}</td>
                                        <td class="py-1.5 pl-2 text-right tabular-nums">{{ LeaveDays::format($row['without_pay']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-4 text-center text-gray-500">{{ __('No approved leave in this period.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>
</x-app-layout>
