<x-app-layout :title="__('My Account')">
    <x-slot name="header">{{ __('My Account') }}</x-slot>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Account Information')" :description="__('Your name and the email address you use to sign in.')">
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card :title="__('Change Password')" :description="__('Use a long, unique password to keep your records secure.')">
            @include('profile.partials.update-password-form')
        </x-card>
    </div>
</x-app-layout>
