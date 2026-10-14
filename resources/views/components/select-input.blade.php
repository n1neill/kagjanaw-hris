@props(['disabled' => false, 'options' => [], 'selected' => null, 'placeholder' => null])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-lg border-line-strong bg-surface text-sm text-ink shadow-xs placeholder:text-ink-subtle focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-ink-muted']) }}>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}</option>
    @endforeach
</select>
