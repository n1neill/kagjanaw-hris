<x-guest-layout>
    <h2 class="text-xl font-semibold tracking-tight text-ink">{{ __('Sign in to your account') }}</h2>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="username" :value="__('Employee ID')" />
            <x-text-input id="username" class="mt-1" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="username-hint" />
            <p id="username-hint" class="mt-1 text-xs text-ink-muted">{{ __('Your employee ID, e.g. 2018-0001. System accounts use their username.') }}</p>
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <x-checkbox id="remember_me" name="remember" :label="__('Remember me')" />

            <a class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300 hover:underline dark:text-brand-400 dark:hover:text-brand-300" href="{{ route('password.request') }}">
                {{ __('Forgot your password?') }}
            </a>
        </div>

        <x-primary-button class="w-full">
            {{ __('Log in') }}
        </x-primary-button>
    </form>
</x-guest-layout>
