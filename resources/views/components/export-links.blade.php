@props(['route', 'parameters' => []])

{{-- Excel, CSV and PDF downloads of a report; the route takes a {format} parameter. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }}>
    <x-button-link :href="route($route, [...$parameters, 'format' => 'xlsx'])" variant="secondary">{{ __('Excel') }}</x-button-link>
    <x-button-link :href="route($route, [...$parameters, 'format' => 'csv'])" variant="secondary">{{ __('CSV') }}</x-button-link>
    <x-button-link :href="route($route, [...$parameters, 'format' => 'pdf'])" variant="secondary" target="_blank" rel="noopener">{{ __('PDF') }}</x-button-link>
</div>
