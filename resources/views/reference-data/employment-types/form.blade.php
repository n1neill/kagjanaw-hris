@php($isNew = ! $employmentType->exists)

<x-app-layout :title="$isNew ? __('New Employment Type') : __('Edit Employment Type')">
    <x-slot name="header">{{ $isNew ? __('New Employment Type') : __('Edit Employment Type') }}</x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $isNew ? route('reference-data.employment-types.store') : route('reference-data.employment-types.update', $employmentType) }}" class="space-y-5">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $employmentType->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="code" :value="__('Code')" />
                    <x-text-input id="code" name="code" type="text" class="mt-1" maxlength="30" :value="old('code', $employmentType->code)" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="description" :value="__('Description (optional)')" />
                <x-textarea-input id="description" name="description" class="mt-1">{{ old('description', $employmentType->description) }}</x-textarea-input>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <x-checkbox name="is_active" value="1" :checked="old('is_active', $employmentType->is_active)" :label="__('Active')" />

            <div class="flex items-center gap-3">
                <x-primary-button>{{ $isNew ? __('Add Employment Type') : __('Save Changes') }}</x-primary-button>
                <x-button-link :href="route('reference-data.employment-types.index')" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
