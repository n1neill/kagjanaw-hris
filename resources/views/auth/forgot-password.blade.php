<x-guest-layout>
    <h2 class="text-lg font-semibold text-gray-900">{{ __('Reset your password') }}</h2>
    <p class="mt-1 text-sm text-gray-600">
        {{ __('Enter your email address and we will send you a password reset link. If you no longer have access to your email, ask the System Administrator to reset your password.') }}
    </p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">
            {{ __('Email Password Reset Link') }}
        </x-primary-button>

        <p class="text-center">
            <a href="{{ route('login') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900 hover:underline">{{ __('Back to sign in') }}</a>
        </p>
    </form>
</x-guest-layout>
