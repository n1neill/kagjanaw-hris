@php($isNew = ! $announcement->exists)
@php($selectedDepartments = collect(old('departments', $isNew ? [] : $announcement->departments->pluck('id')->all()))->map(fn ($id) => (int) $id)->all())

<x-app-layout :title="$isNew ? __('New Announcement') : __('Edit Announcement')">
    <x-slot name="header">{{ $isNew ? __('New Announcement') : __('Edit Announcement') }}</x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ $isNew ? route('announcements.store') : route('announcements.update', $announcement) }}" class="space-y-5"
              x-data="{ audience: @js(old('audience', $announcement->audience?->value ?? 'everyone')) }">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            <div>
                <x-input-label for="title" :value="__('Title')" />
                <x-text-input id="title" name="title" class="mt-1" maxlength="150" :value="old('title', $announcement->title)" required autofocus />
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="body" :value="__('Message')" />
                <x-textarea-input id="body" name="body" rows="10" class="mt-1" maxlength="5000" required>{{ old('body', $announcement->body) }}</x-textarea-input>
                <p class="mt-1 text-xs text-ink-muted">{{ __('Plain text. Line breaks are kept.') }}</p>
                <x-input-error :messages="$errors->get('body')" class="mt-2" />
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-ink">{{ __('Addressed to') }}</legend>
                <div class="mt-2 flex flex-wrap gap-4">
                    @foreach ($audiences as $value => $label)
                        <label class="inline-flex items-center gap-2 text-sm text-ink">
                            <input type="radio" name="audience" value="{{ $value }}" x-model="audience" class="border-line-strong bg-surface text-brand-700 focus:ring-brand-500 focus:ring-offset-surface">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('audience')" class="mt-2" />

                <div x-show="audience === 'departments'" x-cloak class="mt-3 grid gap-2 rounded-lg border border-line p-3 sm:grid-cols-2">
                    @foreach ($departments as $id => $name)
                        <x-checkbox name="departments[]" :value="$id" :checked="in_array($id, $selectedDepartments, true)" :label="$name" />
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('departments')" class="mt-2" />
                <x-input-error :messages="$errors->get('departments.*')" class="mt-2" />
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="expires_on" :value="__('Show until (optional)')" />
                    <x-text-input id="expires_on" name="expires_on" type="date" class="mt-1" :value="old('expires_on', $announcement->expires_on?->toDateString())" />
                    <x-input-error :messages="$errors->get('expires_on')" class="mt-2" />
                </div>
                <div class="flex items-end pb-2">
                    <x-checkbox name="is_pinned" value="1" :checked="old('is_pinned', $announcement->is_pinned)" :label="__('Pin to the top')" />
                </div>
            </div>

            @if ($isNew || ! $announcement->isPublished())
                <p class="text-sm text-ink-muted">{{ __('Publishing notifies everyone it is addressed to.') }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                @if ($isNew || $announcement->published_at === null)
                    <x-primary-button name="action" value="publish">{{ __('Publish') }}</x-primary-button>
                    <x-secondary-button type="submit" name="action" value="draft">{{ __('Save as Draft') }}</x-secondary-button>
                @else
                    <x-primary-button name="action" value="publish">{{ __('Save Changes') }}</x-primary-button>
                @endif
                <x-button-link :href="$isNew ? route('announcements.index') : route('announcements.show', $announcement)" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
