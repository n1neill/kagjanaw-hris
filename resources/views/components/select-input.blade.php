@props(['disabled' => false, 'options' => [], 'selected' => null, 'placeholder' => null])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-100 disabled:text-gray-500']) }}>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}</option>
    @endforeach
</select>
