<x-app-layout :title="__('Import Opening Balances')">
    <x-slot name="header">{{ __('Import Opening Balances') }}</x-slot>

    @if ($result = session('importResult'))
        <x-card :title="__('Import results — :file', ['file' => $result['filename']])" :description="__('Balances as of :date', ['date' => $result['asOf']])">
            <dl class="grid gap-4 sm:grid-cols-3">
                <x-stat-card :label="__('Rows read')" :value="$result['rows']" />
                <x-stat-card :label="__('Employees imported')" :value="$result['imported']" />
                <x-stat-card :label="__('Rows skipped')" :value="count($result['errors'])" />
            </dl>

            @if ($result['errors'] !== [])
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach (array_slice($result['errors'], 0, 50) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    @if (count($result['errors']) > 50)
                        <p class="mt-2">{{ __('…and :count more.', ['count' => count($result['errors']) - 50]) }}</p>
                    @endif
                </div>
            @endif
        </x-card>
    @endif

    <x-card :title="__('Upload balances')" :description="__('Use this once, when the system starts keeping the leave cards. Enter each employee\'s vacation and sick leave balance from their paper leave card as of the end of a month; monthly credits are then posted from the following month.')">
        <x-slot name="actions">
            <x-button-link :href="route('leave.opening-balances.template')" variant="secondary">{{ __('Download template') }}</x-button-link>
        </x-slot>

        <div class="mb-6 rounded-md bg-gray-50 p-4 text-sm text-gray-700">
            <p class="font-semibold">{{ __('File format') }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>{{ __('The first row is the header:') }} <code class="font-mono">{{ implode(',', $columns) }}</code></li>
                <li>{{ __('One employee per row, by employee ID. Balances in days with up to three decimals, e.g.') }} <code class="font-mono">2021-0230,45.250,60.000</code></li>
                <li>{{ __('Employees who already have leave card entries are skipped; correct them with an adjustment on their leave card.') }}</li>
                <li>{{ __('Up to :max rows per file.', ['max' => number_format($maxRows)]) }}</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('leave.opening-balances.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-4">
            @csrf
            <div>
                <x-input-label for="as_of" :value="__('Balances as of (last day of a month)')" />
                <x-text-input id="as_of" name="as_of" type="date" class="mt-1" :value="old('as_of', $suggestedAsOf)" required />
                <x-input-error :messages="$errors->get('as_of')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="file" :value="__('CSV file (up to 2 MB)')" />
                <input id="file" name="file" type="file" accept=".csv,text/csv" required class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                <x-input-error :messages="$errors->get('file')" class="mt-2" />
            </div>
            <x-primary-button>{{ __('Import') }}</x-primary-button>
        </form>
    </x-card>
</x-app-layout>
