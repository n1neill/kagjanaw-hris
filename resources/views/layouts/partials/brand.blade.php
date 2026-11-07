<a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" :title="sidebarCollapsed ? 'Cagdianao HRIS' : null">
    <x-application-logo class="size-9 shrink-0" />
    <span class="leading-tight" :class="sidebarCollapsed && 'lg:sr-only'">
        <span class="block text-sm font-semibold text-ink">Cagdianao HRIS</span>
        <span class="block text-xs text-ink-muted">{{ __('Municipality of Cagdianao') }}</span>
    </span>
</a>
