<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-line-strong bg-surface px-4 py-2 text-sm font-semibold text-ink shadow-xs transition hover:bg-surface-muted focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
