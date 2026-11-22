@props(['permission'])

{{-- Shown on HR pages to a user who holds the page's permission as a granted extra, not through their role. --}}
<x-alert type="warning" {{ $attributes }}>
    <p class="font-medium">{{ __('You are using additional access (:permission).', ['permission' => __($permission->label())]) }}</p>
    <p class="mt-0.5">
        @if ($permission === \App\Enums\Permission::ProcessPayroll)
            {{ __('Payroll runs you compute are approved and released by an HR Officer. Other changes you make here are sent to an HR Officer for approval before they are recorded.') }}
        @else
            {{ __('Changes you make here are sent to an HR Officer for approval before they are recorded.') }}
        @endif
        <a href="{{ route('approvals.index') }}" class="font-medium underline">{{ __('My submissions') }}</a>
    </p>
</x-alert>
