<x-app-layout :title="__('Edit Account')">
    <x-slot name="header">{{ __('Edit Account') }}</x-slot>

    <x-card class="max-w-2xl" :title="$user->name">
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
</x-app-layout>
