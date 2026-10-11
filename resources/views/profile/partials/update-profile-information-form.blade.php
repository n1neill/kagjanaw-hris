@php($isEmployeeAccount = $user->employee()->exists())

<form method="post" action="{{ route('profile.update') }}" class="space-y-5">
    @csrf
    @method('patch')

    <div>
        <x-input-label for="username" :value="$isEmployeeAccount ? __('Employee ID') : __('Username')" />
        <x-text-input id="username" type="text" class="mt-1" :value="$user->username" disabled />
        <p class="mt-1 text-xs text-gray-500">{{ __('You sign in with this. Only HR or the System Administrator can change it.') }}</p>
    </div>

    <div>
        <x-input-label for="name" :value="__('Name')" />
        @if ($isEmployeeAccount)
            <x-text-input id="name" type="text" class="mt-1" :value="$user->name" disabled />
            <p class="mt-1 text-xs text-gray-500">{{ __('Your name comes from your employee record. Ask the HR office to correct it.') }}</p>
        @else
            <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $user->name)" required autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        @endif
    </div>

    <div>
        <x-input-label for="email" :value="__('Email (optional)')" />
        <x-text-input id="email" name="email" type="email" class="mt-1" :value="old('email', $user->email)" autocomplete="email" aria-describedby="email-hint" />
        <p id="email-hint" class="mt-1 text-xs text-gray-500">{{ __('Used to send you a password reset link if you forget your password.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('email')" />
    </div>

    <x-primary-button>{{ __('Save') }}</x-primary-button>
</form>
