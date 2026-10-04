@props(['disabled' => false])

<textarea @disabled($disabled) {{ $attributes->merge(['rows' => 3, 'class' => 'block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-100 disabled:text-gray-500']) }}>{{ $slot }}</textarea>
