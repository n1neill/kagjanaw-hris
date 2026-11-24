<x-guest-layout>
    <h2 class="text-xl font-semibold tracking-tight text-ink">{{ __('Check your email') }}</h2>
    <p class="mt-1 text-sm text-ink-muted">
        {{ __('We sent a 6-digit sign-in code to :email. Enter it below to finish signing in. The code expires in :minutes minutes.', ['email' => $maskedEmail, 'minutes' => $codeLifetime]) }}
    </p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Sign-in code')" />
            <x-text-input id="code" class="mt-1 text-center text-lg tracking-[0.5em]" type="text" name="code" inputmode="numeric" maxlength="7" required autofocus autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">
            {{ __('Verify and Sign In') }}
        </x-primary-button>
    </form>

    <form method="POST" action="{{ route('two-factor.resend') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300 hover:underline">{{ __('Send a new code') }}</button>
    </form>

    <p class="mt-2 text-center">
        <a href="{{ route('login') }}" class="text-sm font-medium text-ink-muted hover:text-ink hover:underline">{{ __('Back to sign in') }}</a>
    </p>
</x-guest-layout>
