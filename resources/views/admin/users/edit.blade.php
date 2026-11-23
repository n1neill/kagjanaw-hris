<x-app-layout :title="__('Edit Account')">
    <x-slot name="header">{{ __('Edit Account') }}</x-slot>

    <div class="grid items-start gap-6 xl:grid-cols-2">
    <x-card :title="$user->name">
        <x-slot name="actions">
            <x-status-badge :active="$user->is_active" />
        </x-slot>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
            @csrf
            @method('PUT')

            @include('admin.users.partials.form', ['canChangeRole' => Auth::user()->can('changeRole', $user)])

            <div class="flex items-center gap-3">
                <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                <x-button-link :href="route('admin.users.index')" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>

    @can('changePermissions', $user)
        <x-card :title="__('Additional access')" :description="__('Let this account do HR work beyond its role. Access takes effect as soon as you save, but every change the user makes with it is sent to an HR Officer for approval before it is recorded.')">
            <form method="POST" action="{{ route('admin.users.permissions', $user) }}" class="space-y-5">
                @csrf
                @method('PUT')

                @php($selectedPermissions = old('permissions', array_map(fn ($permission) => $permission->value, $grantedPermissions)))

                <ul class="divide-y divide-line/70">
                    @foreach ($grantablePermissions as $permission)
                        @php($isInRole = in_array($permission, $rolePermissions, true))
                        <li class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                            <input
                                type="checkbox"
                                id="permission-{{ $permission->value }}"
                                @if (! $isInRole) name="permissions[]" value="{{ $permission->value }}" @endif
                                @checked($isInRole || in_array($permission->value, $selectedPermissions, true))
                                @disabled($isInRole)
                                aria-describedby="permission-{{ $permission->value }}-description"
                                class="mt-0.5 size-4 rounded border-line-strong bg-surface text-brand-700 shadow-xs checked:border-brand-700 checked:bg-brand-700 disabled:opacity-50 dark:checked:border-brand-500 dark:checked:bg-brand-500 focus:ring-brand-500 focus:ring-offset-surface"
                            >
                            <div class="min-w-0">
                                <label for="permission-{{ $permission->value }}" class="flex flex-wrap items-center gap-2 text-sm font-medium text-ink">
                                    {{ __($permission->label()) }}
                                    @if ($isInRole)
                                        <x-badge>{{ __('Included in role') }}</x-badge>
                                    @endif
                                </label>
                                <p id="permission-{{ $permission->value }}-description" class="mt-0.5 text-xs text-ink-muted">{{ __($permission->description()) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <x-input-error :messages="$errors->get('permissions')" />
                <x-input-error :messages="$errors->get('permissions.*')" />

                <div class="flex items-center gap-3">
                    <x-primary-button>{{ __('Save Access') }}</x-primary-button>
                </div>
            </form>
        </x-card>
    @endcan
    </div>
</x-app-layout>
