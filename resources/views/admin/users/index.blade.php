<x-app-layout :title="__('User Accounts')">
    <x-slot name="header">{{ __('User Accounts') }}</x-slot>

    <x-card :title="__('All accounts')" :description="__('Each account holds one role. Employee accounts are created automatically when HR adds an employee. Deactivated accounts cannot sign in.')">
        <x-slot name="actions">
            <x-button-link :href="route('admin.users.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('New Account') }}
            </x-button-link>
        </x-slot>

        <x-data-table :empty="__('No user accounts yet.')">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Username') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Last login') }}</th>
                    <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td class="font-medium text-ink">{{ $user->name }}</td>
                        <td class="font-mono text-xs">{{ $user->username }}</td>
                        <td>{{ $user->email ?? '—' }}</td>
                        <td>
                            @if ($role = $user->primaryRole())
                                <x-badge color="brand">{{ $role->label() }}</x-badge>
                            @else
                                <x-badge color="yellow">{{ __('No role') }}</x-badge>
                            @endif
                        </td>
                        <td><x-status-badge :active="$user->is_active" /></td>
                        <td data-order="{{ $user->last_login_at?->timestamp ?? 0 }}">
                            {{ $user->last_login_at?->format('M j, Y g:i A') ?? __('Never') }}
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.users.edit', $user) }}" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('Edit') }}</a>

                                @can('changeStatus', $user)
                                    <form method="POST" action="{{ route('admin.users.status', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                                        <button type="submit" class="text-sm font-medium {{ $user->is_active ? 'text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300' : 'text-green-700 dark:text-green-300 hover:text-green-900 dark:hover:text-green-300' }}">
                                            {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
    </x-card>
</x-app-layout>
