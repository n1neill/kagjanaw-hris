<x-app-layout :title="__('Leave Endorsements')">
    <x-slot name="header">{{ __('Leave Endorsements') }}</x-slot>

    <x-card :title="__('Waiting for your endorsement')" :description="__('Endorse or reject each application with your remarks. Endorsed applications go to HR for approval.')">
        @include('leave.partials.requests-table', ['requests' => $awaiting, 'showEmployee' => true, 'empty' => __('No applications are waiting for your endorsement.')])
    </x-card>

    <x-card :title="__('Recently acted on')">
        @include('leave.partials.requests-table', ['requests' => $recent, 'showEmployee' => true, 'empty' => __('You have not acted on any applications yet.')])
    </x-card>
</x-app-layout>
