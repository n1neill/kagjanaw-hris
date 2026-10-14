<x-app-layout :title="__('Reports')">
    <x-slot name="header">{{ __('Reports') }}</x-slot>

    <p class="text-sm text-gray-600">
        {{ $seesAll ? __('Every report can be filtered by office and downloaded as Excel, CSV or PDF.') : __('Reports cover your own office. Each can be downloaded as Excel, CSV or PDF.') }}
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($reports as $report)
            <a href="{{ $report['route'] }}" class="flex items-start gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-xs hover:border-brand-300">
                <x-icon :name="$report['icon']" class="mt-0.5 size-6 shrink-0 text-brand-700" />
                <span>
                    <span class="block text-sm font-semibold text-gray-900">{{ $report['title'] }}</span>
                    <span class="mt-1 block text-sm text-gray-600">{{ $report['description'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</x-app-layout>
