@use('App\Enums\ThemePreference')

{{-- A plain form, since inline scripts are blocked by the Content Security Policy. --}}
<form method="POST" action="{{ route('profile.theme') }}" class="px-4 py-3">
    @csrf
    @method('PATCH')

    <p id="theme-switcher-label" class="text-xs font-medium text-ink-muted">{{ __('Appearance') }}</p>
    <div class="mt-2 grid grid-cols-3 gap-1 rounded-lg bg-surface-muted p-1" role="group" aria-labelledby="theme-switcher-label">
        @foreach (ThemePreference::cases() as $theme)
            @php($isCurrent = Auth::user()->theme === $theme)
            <button type="submit" name="theme" value="{{ $theme->value }}"
                    @class([
                        'flex flex-col items-center gap-1 rounded-md px-2 py-1.5 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-brand-500',
                        'bg-surface text-brand-700 shadow-xs ring-1 ring-line dark:text-brand-300' => $isCurrent,
                        'text-ink-muted hover:text-ink' => ! $isCurrent,
                    ])
                    aria-pressed="{{ $isCurrent ? 'true' : 'false' }}">
                <x-icon :name="$theme->icon()" class="size-4" />
                {{ __($theme->label()) }}
            </button>
        @endforeach
    </div>
</form>
