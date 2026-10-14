@props(['data', 'label', 'height' => 'h-64', 'empty' => null])

{{--
    A chart drawn by resources/js/charts.js from DashboardChartService data.
    The data travels as JSON in the canvas's data-chart attribute, since the
    Content Security Policy refuses inline scripts. A table view carries
    every value for screen readers and anyone who prefers numbers.
--}}
@use('App\Services\DashboardChartService')

@php
$isSingleRow = $data['type'] === 'bar-stacked';
$swatches = [
    'ontime' => 'bg-(--chart-ontime)',
    'late' => 'bg-(--chart-late)',
    'leave' => 'bg-(--chart-leave)',
    'absent' => 'bg-(--chart-absent)',
    'primary' => 'bg-(--chart-primary)',
    'secondary' => 'bg-(--chart-secondary)',
];
$format = fn (int|float $value): string => match ($data['unit']) {
    'peso' => '₱'.number_format($value, 2),
    default => fmod((float) $value, 1.0) === 0.0 ? number_format($value) : number_format($value, 1),
};
@endphp

@if (! DashboardChartService::hasData($data))
    <x-empty-state icon="chart" :title="__('Nothing to chart yet')" :description="$empty" />
@else
    <figure {{ $attributes }}>
        @if (count($data['series']) > 1)
            <ul class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-ink-muted">
                @foreach ($data['series'] as $series)
                    <li class="flex items-center gap-1.5">
                        <span class="size-2.5 shrink-0 rounded-sm {{ $swatches[$series['color']] }}" aria-hidden="true"></span>
                        {{ $series['label'] }}
                        @if ($isSingleRow)
                            <span class="font-semibold text-ink tabular-nums">{{ $format($series['values'][0]) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="relative mt-3 {{ $height }}">
            <canvas data-chart="{{ json_encode($data) }}" role="img" aria-label="{{ $label }}"></canvas>
        </div>

        <details class="group mt-3">
            <summary class="inline-flex cursor-pointer items-center gap-1 rounded text-xs font-medium text-ink-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-brand-500">
                <x-icon name="chevron-right" class="size-3 transition group-open:rotate-90" />
                {{ __('View as table') }}
            </summary>
            <div class="mt-2 max-h-72 overflow-auto rounded-lg border border-line">
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ $label }}</caption>
                    <thead class="sticky top-0 bg-surface-muted text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        <tr>
                            <th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('Category') }}</span></th>
                            @foreach ($data['series'] as $series)
                                <th scope="col" class="px-3 py-2 text-right">{{ $series['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($data['labels'] as $index => $category)
                            <tr>
                                <th scope="row" class="px-3 py-1.5 text-left font-medium text-ink">{{ $category }}</th>
                                @foreach ($data['series'] as $series)
                                    <td class="px-3 py-1.5 text-right text-ink tabular-nums">{{ $format($series['values'][$index]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </figure>
@endif
