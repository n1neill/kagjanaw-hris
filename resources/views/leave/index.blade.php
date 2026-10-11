<x-app-layout :title="__('Leave Applications')">
    <x-slot name="header">{{ __('Leave Applications') }}</x-slot>

    <x-card :title="$status ? $status->label() : __('All applications')" :description="__('Applications endorsed by the department head (or the Mayor) wait here for HR approval.')">
        <form method="GET" action="{{ route('leave.requests.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="status" :value="__('Status')" />
                <x-select-input id="status" name="status" class="mt-1" :options="['all' => __('All statuses'), ...$statuses]" :selected="$status?->value ?? 'all'" />
            </div>
            <div>
                <x-input-label for="department" :value="__('Office')" />
                <x-select-input id="department" name="department" class="mt-1" :options="$departments" :selected="$filters['department'] ?? null" :placeholder="__('All offices')" />
            </div>
            <div>
                <x-input-label for="type" :value="__('Leave type')" />
                <x-select-input id="type" name="type" class="mt-1" :options="$types" :selected="$filters['type'] ?? null" :placeholder="__('All types')" />
            </div>
            <div>
                <x-input-label for="year" :value="__('Year')" />
                <x-text-input id="year" name="year" type="number" min="2000" max="2100" class="mt-1 w-28" :value="$year" />
            </div>
            <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
        </form>

        @include('leave.partials.requests-table', ['showEmployee' => true, 'empty' => __('No applications match these filters.')])
    </x-card>
</x-app-layout>
