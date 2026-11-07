{{-- A sidebar section heading; on the collapsed desktop rail it becomes a thin divider. --}}
<p class="px-3 text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-subtle" :class="sidebarCollapsed && 'lg:hidden'">{{ $slot }}</p>
<hr class="hidden border-line" :class="sidebarCollapsed && 'lg:block'" aria-hidden="true">
