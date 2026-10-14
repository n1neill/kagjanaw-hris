<x-app-layout :title="__('Holidays')">
    <x-slot name="header">{{ __('Holidays') }}</x-slot>

    <x-card :title="__('Non-working holidays')" :description="__('Nobody is marked absent on a holiday. Seeded national holidays must be checked against the yearly presidential proclamation; add local holidays here.')">
        <x-slot name="actions">
            <x-button-link :href="route('work-calendar.holidays.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Holiday') }}
            </x-button-link>
        </x-slot>

        <x-data-table :empty="__('No holidays yet.')">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Holiday') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th data-orderable="false"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($holidays as $holiday)
                    <tr>
                        <td data-order="{{ $holiday->date->toDateString() }}" class="whitespace-nowrap">{{ $holiday->date->format('D, M j, Y') }}</td>
                        <td class="font-medium text-ink">{{ $holiday->name }}</td>
                        <td>{{ $holiday->type->label() }}</td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('work-calendar.holidays.edit', $holiday) }}" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('work-calendar.holidays.destroy', $holiday) }}" data-confirm="{{ __('Remove this holiday? Attendance on that day will be recomputed.') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">{{ __('Remove') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
