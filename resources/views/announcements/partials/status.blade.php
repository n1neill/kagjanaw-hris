{{-- Publication status of an announcement, for the HR Office. --}}
@if (! $announcement->isPublished())
    <x-badge color="yellow">{{ __('Draft') }}</x-badge>
@elseif ($announcement->isExpired())
    <x-badge>{{ __('Expired') }}</x-badge>
@else
    <x-badge color="green">{{ __('Published') }}</x-badge>
@endif
