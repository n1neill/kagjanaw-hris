@props(['active'])

<x-badge :color="$active ? 'green' : 'gray'" {{ $attributes }}>
    {{ $active ? __('Active') : __('Inactive') }}
</x-badge>
