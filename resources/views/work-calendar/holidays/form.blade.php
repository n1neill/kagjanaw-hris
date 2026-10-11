@php($isNew = ! $holiday->exists)

<x-app-layout :title="$isNew ? __('New Holiday') : __('Edit Holiday')">
    <x-slot name="header">{{ $isNew ? __('New Holiday') : __('Edit Holiday') }}</x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $isNew ? route('work-calendar.holidays.store') : route('work-calendar.holidays.update', $holiday) }}" class="space-y-5">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <x-input-label for="date" :value="__('Date')" />
                    <x-text-input id="date" name="date" type="date" class="mt-1" :value="old('date', $holiday->date?->toDateString())" required autofocus />
                    <x-input-error :messages="$errors->get('date')" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" class="mt-1" placeholder="Cagdianao Town Fiesta" :value="old('name', $holiday->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div class="sm:col-span-3">
                    <x-input-label for="type" :value="__('Type')" />
                    <x-select-input id="type" name="type" class="mt-1" :options="$types" :selected="old('type', $holiday->type?->value)" :placeholder="__('Select type')" required />
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>{{ $isNew ? __('Add Holiday') : __('Save Changes') }}</x-primary-button>
                <x-button-link :href="route('work-calendar.holidays.index')" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
