@php($isNew = ! $position->exists)

<x-app-layout :title="$isNew ? __('New Position') : __('Edit Position')">
    <x-slot name="header">{{ $isNew ? __('New Position') : __('Edit Position') }}</x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $isNew ? route('reference-data.positions.store') : route('reference-data.positions.update', $position) }}" class="space-y-5">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            <div class="grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input-label for="title" :value="__('Position title')" />
                    <x-text-input id="title" name="title" type="text" class="mt-1" :value="old('title', $position->title)" required autofocus />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="salary_grade" :value="__('Salary grade')" />
                    <x-text-input id="salary_grade" name="salary_grade" type="number" min="1" max="33" class="mt-1" :value="old('salary_grade', $position->salary_grade)" />
                    <x-input-error :messages="$errors->get('salary_grade')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="description" :value="__('Description (optional)')" />
                <x-textarea-input id="description" name="description" class="mt-1">{{ old('description', $position->description) }}</x-textarea-input>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <x-checkbox name="is_active" value="1" :checked="old('is_active', $position->is_active)" :label="__('Active')" />

            <div class="flex items-center gap-3">
                <x-primary-button>{{ $isNew ? __('Add Position') : __('Save Changes') }}</x-primary-button>
                <x-button-link :href="route('reference-data.positions.index')" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
