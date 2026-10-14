@php($display = fn (mixed $value): string => match (true) {
    $value === null => '—',
    is_bool($value) => $value ? __('Yes') : __('No'),
    is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    default => (string) $value,
})

<x-app-layout :title="__('Audit Log Entry')">
    <x-slot name="header">{{ __('Audit Log Entry') }}</x-slot>

    <x-card>
        <x-slot name="actions">
            <x-button-link :href="route('admin.audit-logs.index')" variant="secondary">{{ __('Back') }}</x-button-link>
        </x-slot>

        <dl class="grid gap-4 sm:grid-cols-3">
            <x-detail :label="__('Time')"><span class="font-mono">{{ $entry->created_at->format('Y-m-d H:i:s') }}</span></x-detail>
            <x-detail :label="__('User')">{{ $entry->user ? $entry->user->name.' ('.$entry->user->username.')' : __('System or visitor') }}</x-detail>
            <x-detail :label="__('Action')"><x-badge :color="$entry->event->badgeColor()">{{ $entry->event->label() }}</x-badge></x-detail>
            <x-detail :label="__('Record')" class="sm:col-span-2">
                @if ($entry->subjectType())
                    {{ $entry->subjectType() }} #{{ $entry->auditable_id }} ·
                @endif
                {{ $entry->description }}
            </x-detail>
            <x-detail :label="__('IP address')"><span class="font-mono">{{ $entry->ip_address }}</span></x-detail>
            <x-detail :label="__('Browser')" class="sm:col-span-3">{{ $entry->user_agent }}</x-detail>
        </dl>
    </x-card>

    @if ($changes->isNotEmpty())
        <x-card :title="__('Values')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-3">{{ __('Field') }}</th>
                            @if ($entry->old_values !== null)
                                <th class="px-2 py-2">{{ __('Before') }}</th>
                            @endif
                            @if ($entry->new_values !== null)
                                <th class="px-2 py-2">{{ __('After') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($changes as $change)
                            <tr class="border-b border-gray-100 align-top">
                                <td class="py-2 pr-3 font-mono text-xs text-gray-600">{{ $change['field'] }}</td>
                                @if ($entry->old_values !== null)
                                    <td class="break-all px-2 py-2">{{ $display($change['old']) }}</td>
                                @endif
                                @if ($entry->new_values !== null)
                                    <td class="break-all px-2 py-2">{{ $display($change['new']) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</x-app-layout>
