@props(['label', 'checked' => false])

<label class="inline-flex items-center gap-2 text-sm text-gray-700">
    <input type="checkbox" @checked($checked) {{ $attributes->merge(['class' => 'rounded-sm border-gray-300 text-brand-700 shadow-xs focus:ring-brand-500']) }}>
    <span>{{ $label }}</span>
</label>
