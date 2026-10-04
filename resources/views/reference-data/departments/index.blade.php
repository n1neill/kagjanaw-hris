<x-app-layout :title="__('Departments')">
    <x-slot name="header">{{ __('Departments') }}</x-slot>

    <x-card :title="__('Offices and departments')" :description="__('Deactivate an office instead of deleting it so past records keep their department.')">
        <x-slot name="actions">
            <x-button-link :href="route('reference-data.departments.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Department') }}
            </x-button-link>
        </x-slot>

        <x-data-table :empty="__('No departments yet.')">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($departments as $department)
                    <tr>
                        <td class="font-mono text-xs font-semibold text-gray-700">{{ $department->code }}</td>
                        <td class="font-medium text-gray-900">{{ $department->name }}</td>
                        <td><x-status-badge :active="$department->is_active" /></td>
                        <td class="text-right">
                            <a href="{{ route('reference-data.departments.edit', $department) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
