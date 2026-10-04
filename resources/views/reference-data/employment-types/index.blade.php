<x-app-layout :title="__('Employment Types')">
    <x-slot name="header">{{ __('Employment Types') }}</x-slot>

    <x-card :title="__('Natures of appointment')" :description="__('Used to classify employees, e.g. Permanent, Casual or Job Order.')">
        <x-slot name="actions">
            <x-button-link :href="route('reference-data.employment-types.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Employment Type') }}
            </x-button-link>
        </x-slot>

        <x-data-table :empty="__('No employment types yet.')">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($employmentTypes as $employmentType)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $employmentType->name }}</td>
                        <td class="font-mono text-xs text-gray-700">{{ $employmentType->code }}</td>
                        <td class="text-gray-600">{{ $employmentType->description }}</td>
                        <td><x-status-badge :active="$employmentType->is_active" /></td>
                        <td class="text-right">
                            <a href="{{ route('reference-data.employment-types.edit', $employmentType) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
