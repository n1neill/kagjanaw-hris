@props(['label', 'checked' => false])

<label class="inline-flex items-center gap-2 text-sm text-ink">
    <input type="checkbox" @checked($checked) {{ $attributes->merge(['class' => 'size-4 rounded border-line-strong bg-surface text-brand-700 shadow-xs focus:ring-brand-500 focus:ring-offset-surface']) }}>
    <span>{{ $label }}</span>
</label>
