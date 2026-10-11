@use('App\Enums\Permission')

{{-- Menu items are shown per permission; routes enforce the same permissions server-side. --}}
<nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-3 py-4" aria-label="{{ __('Main') }}">
    <div class="space-y-1">
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">
            {{ __('Dashboard') }}
        </x-sidebar-link>
        @can(Permission::ViewOwnRecords)
            <x-sidebar-link :href="route('my-record')" :active="request()->routeIs('my-record')" icon="identification">
                {{ __('My Record') }}
            </x-sidebar-link>
        @endcan
    </div>

    @can(Permission::ManageEmployees)
        <div>
            <p class="px-3 text-xs font-semibold uppercase tracking-wider text-brand-300">{{ __('Human Resources') }}</p>
            <div class="mt-2 space-y-1">
                <x-sidebar-link :href="route('employees.index')" :active="request()->routeIs('employees.*')" icon="users">
                    {{ __('Employees') }}
                </x-sidebar-link>
            </div>
        </div>
    @elsecan(Permission::ViewDepartmentEmployees)
        <div>
            <p class="px-3 text-xs font-semibold uppercase tracking-wider text-brand-300">{{ __('Department') }}</p>
            <div class="mt-2 space-y-1">
                <x-sidebar-link :href="route('employees.index')" :active="request()->routeIs('employees.*')" icon="users">
                    {{ __('My Department') }}
                </x-sidebar-link>
            </div>
        </div>
    @endcan

    @can(Permission::ManageReferenceData)
        <div>
            <p class="px-3 text-xs font-semibold uppercase tracking-wider text-brand-300">{{ __('Reference Data') }}</p>
            <div class="mt-2 space-y-1">
                <x-sidebar-link :href="route('reference-data.departments.index')" :active="request()->routeIs('reference-data.departments.*')" icon="building">
                    {{ __('Departments') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('reference-data.positions.index')" :active="request()->routeIs('reference-data.positions.*')" icon="briefcase">
                    {{ __('Positions') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('reference-data.employment-types.index')" :active="request()->routeIs('reference-data.employment-types.*')" icon="tag">
                    {{ __('Employment Types') }}
                </x-sidebar-link>
            </div>
        </div>
    @endcan

    @can(Permission::ManageUsers)
        <div>
            <p class="px-3 text-xs font-semibold uppercase tracking-wider text-brand-300">{{ __('Administration') }}</p>
            <div class="mt-2 space-y-1">
                <x-sidebar-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="users">
                    {{ __('User Accounts') }}
                </x-sidebar-link>
            </div>
        </div>
    @endcan

    <div class="mt-auto space-y-1 border-t border-brand-800 pt-4">
        <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="user">
            {{ __('My Account') }}
        </x-sidebar-link>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-brand-100 transition hover:bg-brand-800/60 hover:text-white">
                <x-icon name="logout" class="size-5 shrink-0" />
                <span>{{ __('Log Out') }}</span>
            </button>
        </form>
    </div>
</nav>
