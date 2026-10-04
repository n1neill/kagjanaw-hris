<x-app-layout :title="__('New Account')">
    <x-slot name="header">{{ __('New Account') }}</x-slot>

    <x-card class="max-w-2xl" :title="__('Account details')" :description="__('Give the initial password to the user in person and ask them to change it after signing in.')">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
            @csrf

            @include('admin.users.partials.form', ['user' => null, 'canChangeRole' => true, 'currentRole' => null])

            <div class="flex items-center gap-3">
                <x-primary-button>{{ __('Create Account') }}</x-primary-button>
                <x-button-link :href="route('admin.users.index')" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
