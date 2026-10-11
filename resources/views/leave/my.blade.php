<x-app-layout :title="__('My Leave')">
    <x-slot name="header">{{ __('My Leave') }}</x-slot>

    <x-card :title="__('Leave balances')" :description="__('As shown on your leave card. Credits are earned at the end of each month once your DTR is validated.')">
        <x-slot name="actions">
            <x-button-link :href="route('leave.card', $employee)" variant="secondary">
                <x-icon name="book" class="size-4" />
                {{ __('My Leave Card') }}
            </x-button-link>
            <x-button-link :href="route('leave.requests.create')">
                <x-icon name="plus" class="size-4" />
                {{ __('Apply for Leave') }}
            </x-button-link>
        </x-slot>

        @include('leave.partials.balances')
    </x-card>

    <x-card :title="__('My applications')">
        @include('leave.partials.requests-table', ['showEmployee' => false, 'empty' => __('You have not applied for leave yet.')])
    </x-card>
</x-app-layout>
