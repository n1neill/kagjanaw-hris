{{--
    Shared fields for creating and editing a user account.
    Expects: $user (User|null), $roles (array<string, string>), $canChangeRole (bool), $currentRole (Role|null)
--}}
@php($linkedEmployee = $user?->employee)

@if ($linkedEmployee)
    <div class="rounded-md border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
        {{ __('This account belongs to employee :number. Its name and username follow the employee record.', ['number' => $linkedEmployee->employee_number]) }}
    </div>
@endif

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <x-input-label for="name" :value="__('Full name')" />
        @if ($linkedEmployee)
            <x-text-input id="name" type="text" class="mt-1" :value="$user->name" disabled />
        @else
            <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $user?->name)" required autofocus autocomplete="off" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        @endif
    </div>

    <div>
        <x-input-label for="username" :value="__('Username')" />
        @if ($linkedEmployee)
            <x-text-input id="username" type="text" class="mt-1" :value="$user->username" disabled />
        @else
            <x-text-input id="username" name="username" type="text" class="mt-1" :value="old('username', $user?->username)" required autocomplete="off" autocapitalize="none" spellcheck="false" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        @endif
    </div>
</div>

<div>
    <x-input-label for="email" :value="__('Email (optional)')" />
    <x-text-input id="email" name="email" type="email" class="mt-1" :value="old('email', $user?->email)" autocomplete="off" />
    <x-input-error :messages="$errors->get('email')" class="mt-2" />
</div>

<div>
    <x-input-label for="role" :value="__('Role')" />
    @if ($canChangeRole)
        <x-select-input id="role" name="role" class="mt-1" :options="$roles" :selected="old('role', $currentRole?->value)" :placeholder="__('Select a role')" required />
    @else
        <x-select-input id="role" class="mt-1" :options="$roles" :selected="$currentRole?->value" disabled />
        <p class="mt-1 text-xs text-gray-500">{{ __('You cannot change the role of your own account.') }}</p>
    @endif
    <x-input-error :messages="$errors->get('role')" class="mt-2" />
</div>

<fieldset class="space-y-4 rounded-md border border-gray-200 p-4">
    <legend class="px-1 text-sm font-medium text-gray-700">
        {{ $user ? __('Reset password (optional)') : __('Temporary password') }}
    </legend>

    <p class="text-xs text-gray-500">{{ __('The user must change this password the next time they sign in.') }}</p>

    <div>
        <x-input-label for="password" :value="__('Password')" />
        <x-text-input id="password" name="password" type="password" class="mt-1" :required="$user === null" autocomplete="new-password" aria-describedby="password-hint" />
        <p id="password-hint" class="mt-1 text-xs text-gray-500">
            {{ __('At least 12 characters with upper and lower case letters, a number and a symbol.') }}
            @if ($user)
                {{ __('Leave blank to keep the current password.') }}
            @endif
        </p>
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password_confirmation" :value="__('Confirm password')" />
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1" :required="$user === null" autocomplete="new-password" />
    </div>
</fieldset>
