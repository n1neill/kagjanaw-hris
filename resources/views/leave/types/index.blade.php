<x-app-layout :title="__('Leave Types')">
    <x-slot name="header">{{ __('Leave Types') }}</x-slot>

    <x-card :title="__('Leave types and rules')" :description="__('Seeded from the CSC Omnibus Rules on Leave and special laws. Verify them against current official rules and adjust them here when regulations change.')">
        <x-slot name="actions">
            <x-button-link :href="route('leave.types.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Leave Type') }}
            </x-button-link>
        </x-slot>

        <x-data-table :empty="__('No leave types yet.')">
            <thead>
                <tr>
                    <th>{{ __('Leave type') }}</th>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Charged to') }}</th>
                    <th class="text-right">{{ __('Per year') }}</th>
                    <th class="text-right">{{ __('Per application') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr>
                        <td class="font-medium text-gray-900">
                            {{ $type->name }}
                            @if ($type->eligible_sex)
                                <span class="ml-1 text-xs font-normal text-gray-500">({{ $type->eligible_sex->label() }})</span>
                            @endif
                        </td>
                        <td class="font-mono text-xs">{{ $type->code }}</td>
                        <td class="text-sm">{{ $type->credit_source->label() }}</td>
                        <td class="text-right tabular-nums">{{ $type->annual_days ?? '—' }}</td>
                        <td class="text-right tabular-nums">
                            {{ $type->max_days_per_request ?? '—' }}
                            @if ($type->counts_calendar_days)
                                <span class="block text-xs text-gray-500">{{ __('calendar days') }}</span>
                            @endif
                        </td>
                        <td><x-status-badge :active="$type->is_active" /></td>
                        <td class="text-right">
                            <a href="{{ route('leave.types.edit', $type) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
