<x-guest-layout>
    <h2 class="text-lg font-semibold text-gray-900">{{ __('Confirm your password') }}</h2>
    <p class="mt-1 text-sm text-gray-600">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">
            {{ __('Confirm') }}
        </x-primary-button>
    </form>
</x-guest-layout>
