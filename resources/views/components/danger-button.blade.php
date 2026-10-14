<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-red-500 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface active:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
