@props(['empty' => null])

{{--
    A table progressively enhanced by DataTables (see resources/js/app.js).
    Do not render a "no records" colspan row; DataTables shows the empty message itself.
--}}
<div class="overflow-x-auto">
    <table data-datatable data-empty="{{ $empty ?? __('No records found.') }}" {{ $attributes->merge(['class' => 'display w-full text-sm']) }}>
        {{ $slot }}
    </table>
</div>
