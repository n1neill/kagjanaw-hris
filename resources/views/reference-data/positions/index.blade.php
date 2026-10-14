<x-app-layout :title="__('Positions')">
    <x-slot name="header">{{ __('Positions') }}</x-slot>

    <x-card :title="__('Plantilla positions')" :description="__('Salary grades are defaults; verify them against the current DBM Index of Occupational Services.')">
        <x-slot name="actions">
            <x-button-link :href="route('reference-data.positions.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Position') }}
            </x-button-link>
        </x-slot>

        <x-data-table :empty="__('No positions yet.')">
            <thead>
                <tr>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Salary Grade') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($positions as $position)
                    <tr>
                        <td class="font-medium text-ink">{{ $position->title }}</td>
                        <td data-order="{{ $position->salary_grade ?? 0 }}">{{ $position->salary_grade ? 'SG '.$position->salary_grade : '—' }}</td>
                        <td><x-status-badge :active="$position->is_active" /></td>
                        <td class="text-right">
                            <a href="{{ route('reference-data.positions.edit', $position) }}" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
