<x-guest-layout>
    <h2 class="text-lg font-semibold text-gray-900">{{ __('Set your own password') }}</h2>
    <p class="mt-1 text-sm text-gray-600">
        {{ __('You signed in with a temporary password. Choose a new password that only you know before continuing.') }}
    </p>

    <form method="POST" action="{{ route('password.change.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="current_password" :value="__('Temporary password')" />
            <x-text-input id="current_password" class="mt-1" type="password" name="current_password" required autofocus autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('New password')" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="new-password" aria-describedby="password-hint" />
            <p id="password-hint" class="mt-1 text-xs text-gray-500">{{ __('At least 12 characters with upper and lower case letters, a number and a symbol.') }}</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm new password')" />
            <x-text-input id="password_confirmation" class="mt-1" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <x-primary-button class="w-full">{{ __('Save and Continue') }}</x-primary-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-gray-600 hover:text-gray-900 hover:underline">{{ __('Log out') }}</button>
    </form>
</x-guest-layout>
