@props(['disabled' => false])

@php
    $inputClasses = 'block w-full rounded-lg border-line-strong bg-surface text-sm text-ink shadow-xs placeholder:text-ink-subtle focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-ink-muted';
    $isPasswordField = ($attributes->get('type') === 'password');
@endphp

@if ($isPasswordField)
    <div x-data="{ revealed: false }" class="relative">
        <input @disabled($disabled) :type="revealed ? 'text' : 'password'" {{ $attributes->merge(['class' => $inputClasses.' pr-10']) }}>
        <button type="button" x-on:click="revealed = ! revealed" x-bind:aria-label="revealed ? 'Hide password' : 'Show password'" x-bind:aria-pressed="revealed" aria-label="Show password" class="absolute inset-y-0 right-0 flex items-center rounded-r-lg px-3 text-ink-subtle hover:text-ink focus:outline-none focus-visible:text-brand-600">
            <x-icon name="eye" class="size-5" x-show="! revealed" />
            <x-icon name="eye-slash" class="size-5" x-show="revealed" x-cloak />
        </button>
    </div>
@else
    <input @disabled($disabled) {{ $attributes->merge(['class' => $inputClasses]) }}>
@endif
