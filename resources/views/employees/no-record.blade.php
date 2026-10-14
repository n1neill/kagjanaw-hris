<x-app-layout :title="__('My Record')">
    <x-slot name="header">{{ __('My Record') }}</x-slot>

    <x-card class="max-w-2xl">
        <p class="text-sm text-ink-muted">{{ __('Your account is not linked to an employee record. If you are an employee of the Municipality, please contact the HR office.') }}</p>
    </x-card>
</x-app-layout>
