<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-brand-800 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface active:bg-brand-900 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
