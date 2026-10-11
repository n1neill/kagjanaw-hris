@props(['label'])

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</dt>
    <dd class="mt-1 break-words text-sm text-gray-900">{{ $slot->isEmpty() ? '—' : $slot }}</dd>
</div>
