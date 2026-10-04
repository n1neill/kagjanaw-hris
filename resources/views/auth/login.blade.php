<x-guest-layout>
    <h2 class="text-lg font-semibold text-gray-900">{{ __('Sign in to your account') }}</h2>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <x-checkbox id="remember_me" name="remember" :label="__('Remember me')" />

            <a class="text-sm font-medium text-brand-700 hover:text-brand-900 hover:underline" href="{{ route('password.request') }}">
                {{ __('Forgot your password?') }}
            </a>
        </div>

        <x-primary-button class="w-full">
            {{ __('Log in') }}
        </x-primary-button>
    </form>
</x-guest-layout>
