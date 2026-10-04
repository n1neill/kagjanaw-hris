<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-md bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-brand-800 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 active:bg-brand-900 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
