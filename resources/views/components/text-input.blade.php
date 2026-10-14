@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-lg border-line-strong bg-surface text-sm text-ink shadow-xs placeholder:text-ink-subtle focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-ink-muted']) }}>
