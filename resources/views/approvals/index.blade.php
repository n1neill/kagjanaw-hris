<x-app-layout :title="__('Approvals')">
    <x-slot name="header">{{ __('Approvals') }}</x-slot>

    <div class="space-y-6">
        @if ($isReviewer)
            <x-card :title="__('Waiting for approval')" :description="__('Changes made by users with additional access. Nothing is recorded until you approve it.')">
                @include('approvals.partials.table', [
                    'changes' => $pending,
                    'showRequester' => true,
                    'showReviewer' => false,
                    'empty' => __('No changes are waiting for approval.'),
                ])
            </x-card>

            <x-card :title="__('Recently decided')">
                @include('approvals.partials.table', [
                    'changes' => $decided,
                    'showRequester' => true,
                    'showReviewer' => true,
                    'empty' => __('No changes have been decided yet.'),
                ])
            </x-card>
        @endif

        @if (! $isReviewer || $submissions->isNotEmpty())
            <x-card :title="__('My submissions')" :description="__('Changes you made with additional access are recorded once an HR Officer approves them.')">
                @include('approvals.partials.table', [
                    'changes' => $submissions,
                    'showRequester' => false,
                    'showReviewer' => true,
                    'empty' => __('You have not submitted any changes.'),
                ])
            </x-card>
        @endif
    </div>
</x-app-layout>
