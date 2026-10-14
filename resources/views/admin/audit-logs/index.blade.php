<x-app-layout :title="__('Audit Log')">
    <x-slot name="header">{{ __('Audit Log') }}</x-slot>

    @if ($failedSignInsToday > 0)
        <div role="status" class="rounded-md border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
            {{ trans_choice(':count failed sign-in or lockout today.|:count failed sign-ins or lockouts today.', $failedSignInsToday, ['count' => $failedSignInsToday]) }}
            <a href="{{ route('admin.audit-logs.index', ['category' => 'security', 'from' => today()->toDateString()]) }}" class="font-semibold underline">{{ __('Show them') }}</a>
        </div>
    @endif

    <x-card :title="__('Activity')" :description="__('Changes to records, access to personal data, sign-ins and denied requests, newest first. Government ID numbers and passwords are never recorded.')">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
            <div class="lg:col-span-2">
                <x-input-label for="category" :value="__('Category')" />
                <x-select-input id="category" name="category" class="mt-1" :options="$categories" :selected="$filters['category'] ?? null" :placeholder="__('All')" />
            </div>
            <div>
                <x-input-label for="event" :value="__('Action')" />
                <x-select-input id="event" name="event" class="mt-1" :options="$events" :selected="$filters['event'] ?? null" :placeholder="__('All')" />
            </div>
            <div>
                <x-input-label for="user" :value="__('User')" />
                <x-text-input id="user" name="user" class="mt-1" :placeholder="__('Name or username')" :value="$filters['user'] ?? null" />
            </div>
            <div>
                <x-input-label for="from" :value="__('From')" />
                <x-text-input id="from" name="from" type="date" class="mt-1" :value="$filters['from'] ?? null" />
            </div>
            <div>
                <x-input-label for="to" :value="__('To')" />
                <x-text-input id="to" name="to" type="date" class="mt-1" :value="$filters['to'] ?? null" />
                <x-input-error :messages="$errors->get('to')" class="mt-2" />
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <x-input-label for="search" :value="__('Record')" />
                <x-text-input id="search" name="search" class="mt-1" :placeholder="__('Employee number, name, or record')" :value="$filters['search'] ?? null" />
            </div>
            <div class="flex items-center gap-3 lg:col-span-2">
                <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
                @if ($filters !== [])
                    <a href="{{ route('admin.audit-logs.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Clear') }}</a>
                @endif
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="py-2 pr-3">{{ __('Time') }}</th>
                        <th class="px-2 py-2">{{ __('User') }}</th>
                        <th class="px-2 py-2">{{ __('Action') }}</th>
                        <th class="px-2 py-2">{{ __('Record') }}</th>
                        <th class="px-2 py-2">{{ __('IP address') }}</th>
                        <th class="px-2 py-2"><span class="sr-only">{{ __('Details') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr class="border-b border-gray-100 align-top">
                            <td class="whitespace-nowrap py-2 pr-3 font-mono text-xs">{{ $entry->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="px-2 py-2">{{ $entry->user?->name ?? __('System or visitor') }}</td>
                            <td class="px-2 py-2"><x-badge :color="$entry->event->badgeColor()">{{ $entry->event->label() }}</x-badge></td>
                            <td class="px-2 py-2">
                                @if ($entry->subjectType())
                                    <span class="text-xs text-gray-500">{{ $entry->subjectType() }}</span><br>
                                @endif
                                {{ $entry->description }}
                            </td>
                            <td class="px-2 py-2 font-mono text-xs text-gray-600">{{ $entry->ip_address ?? '—' }}</td>
                            <td class="px-2 py-2 text-right">
                                <a href="{{ route('admin.audit-logs.show', $entry) }}" class="text-brand-700 hover:underline">{{ __('Details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-500">{{ __('No activity matches these filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $entries->links() }}</div>
    </x-card>
</x-app-layout>
