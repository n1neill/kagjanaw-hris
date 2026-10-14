@php($isNew = ! $device->exists)

<x-app-layout :title="$isNew ? __('New Device') : __('Edit Device')">
    <x-slot name="header">{{ $isNew ? __('New Device') : __('Edit Device') }}</x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $isNew ? route('admin.devices.store') : route('admin.devices.update', $device) }}" class="space-y-5">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" class="mt-1" placeholder="Main Lobby Time Clock" :value="old('name', $device->name)" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="location" :value="__('Location (optional)')" />
                    <x-text-input id="location" name="location" class="mt-1" :value="old('location', $device->location)" />
                    <x-input-error :messages="$errors->get('location')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="ip_address" :value="__('IP address')" />
                    <x-text-input id="ip_address" name="ip_address" class="mt-1 font-mono" placeholder="192.168.1.201" :value="old('ip_address', $device->ip_address)" required />
                    <x-input-error :messages="$errors->get('ip_address')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="port" :value="__('Port')" />
                    <x-text-input id="port" name="port" type="number" min="1" max="65535" class="mt-1 font-mono" :value="old('port', $device->port)" required aria-describedby="port-hint" />
                    <p id="port-hint" class="mt-1 text-xs text-ink-muted">{{ __('ZKTeco devices use 4370 by default.') }}</p>
                    <x-input-error :messages="$errors->get('port')" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="driver" :value="__('Driver')" />
                    <x-select-input id="driver" name="driver" class="mt-1" :options="$drivers" :selected="old('driver', $device->driver)" required />
                    <x-input-error :messages="$errors->get('driver')" class="mt-2" />
                </div>
            </div>

            <x-checkbox name="is_active" value="1" :checked="old('is_active', $device->is_active)" :label="__('Active (include in automatic synchronization)')" />

            <div class="flex items-center gap-3">
                <x-primary-button>{{ $isNew ? __('Add Device') : __('Save Changes') }}</x-primary-button>
                <x-button-link :href="$isNew ? route('admin.devices.index') : route('admin.devices.show', $device)" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
