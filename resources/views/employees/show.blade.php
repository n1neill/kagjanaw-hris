@use('App\Enums\Permission')
@use('App\Services\Payroll\Money')

{{--
    An employee record. Sensitive sections (personal details, government IDs,
    salary, address and the 201 file) render only when $canViewSensitive.
--}}
<x-app-layout :title="$isOwnRecord ? __('My Record') : $employee->full_name">
    <x-slot name="header">{{ $isOwnRecord ? __('My Record') : __('Employee Record') }}</x-slot>

    @if (session('temporaryPassword'))
        <div role="alert" class="rounded-lg border border-yellow-300 dark:border-yellow-500/30 bg-yellow-50 dark:bg-yellow-500/10 p-4">
            <div class="flex gap-3">
                <x-icon name="key" class="size-6 shrink-0 text-yellow-700 dark:text-yellow-300" />
                <div class="text-sm text-yellow-900 dark:text-yellow-200">
                    <p class="font-semibold">{{ __('Login account created') }}</p>
                    <p class="mt-1">{{ __('Give these sign-in details to the employee in person. The password is shown only once, and the employee must change it at first sign-in.') }}</p>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-yellow-800 dark:text-yellow-200">{{ __('Employee ID') }}</dt>
                            <dd class="font-mono text-base font-semibold">{{ $employee->employee_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-yellow-800 dark:text-yellow-200">{{ __('Temporary password') }}</dt>
                            <dd class="font-mono text-base font-semibold">{{ session('temporaryPassword') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    @endif

    <section class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-line bg-surface p-4 shadow-xs sm:p-6">
        <div class="flex items-center gap-4">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-500/15 text-xl font-semibold text-brand-800 dark:text-brand-300" aria-hidden="true">
                {{ mb_substr($employee->first_name, 0, 1) }}{{ mb_substr($employee->last_name, 0, 1) }}
            </span>
            <div>
                <h2 class="text-xl font-semibold text-ink">{{ $employee->full_name }}</h2>
                <p class="text-sm text-ink-muted">{{ $employee->position->title }} · {{ $employee->department->name }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <x-badge color="brand" class="font-mono">{{ $employee->employee_number }}</x-badge>
                    <x-badge :color="$employee->employment_status->isInService() ? 'green' : 'gray'">{{ $employee->employment_status->label() }}</x-badge>
                    <x-badge>{{ $employee->employmentType->name }}</x-badge>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($canBrowseEmployees && ! $isOwnRecord)
                <x-button-link :href="route('employees.index')" variant="secondary">{{ __('Back to list') }}</x-button-link>
            @endif
            @can('viewAttendance', $employee)
                <x-button-link :href="$isOwnRecord ? route('attendance.my') : route('attendance.dtr.show', $employee)" variant="secondary">
                    <x-icon name="clock" class="size-4" />
                    {{ __('Attendance') }}
                </x-button-link>
            @endcan
            @can(Permission::ProcessPayroll)
                <x-button-link :href="route('payroll.employees.show', $employee)" variant="secondary">
                    <x-icon name="banknotes" class="size-4" />
                    {{ __('Pay Setup') }}
                </x-button-link>
            @endcan
            @if ($canManage)
                <x-button-link :href="route('employees.edit', $employee)">
                    <x-icon name="pencil" class="size-4" />
                    {{ __('Edit Record') }}
                </x-button-link>
            @endif
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Employment')">
            <dl class="grid gap-4 sm:grid-cols-2">
                <x-detail :label="__('Department')">{{ $employee->department->name }}</x-detail>
                <x-detail :label="__('Position')">{{ $employee->position->title }}</x-detail>
                <x-detail :label="__('Employment type')">{{ $employee->employmentType->name }}</x-detail>
                <x-detail :label="__('Date hired')">{{ $employee->date_hired->format('F j, Y') }}</x-detail>
                @if ($canViewSensitive)
                    <x-detail :label="__('Salary grade / step')">
                        @if ($employee->salary_grade)
                            SG {{ $employee->salary_grade }}, {{ __('Step') }} {{ $employee->salary_step }}
                        @endif
                    </x-detail>
                    @if ($employee->daily_rate !== null)
                        <x-detail :label="__('Daily rate')">{{ Money::format($employee->daily_rate) }}</x-detail>
                    @endif
                    <x-detail :label="__('Biometric user ID')">{{ $employee->biometric_user_id }}</x-detail>
                @endif
                @unless ($employee->employment_status->isInService())
                    <x-detail :label="__('Separation date')">{{ $employee->separation_date?->format('F j, Y') }}</x-detail>
                @endunless
            </dl>
        </x-card>

        <x-card :title="__('Contact')">
            <dl class="grid gap-4 sm:grid-cols-2">
                <x-detail :label="__('Mobile number')">{{ $employee->mobile_number }}</x-detail>
                <x-detail :label="__('Email')">{{ $employee->user?->email }}</x-detail>
                @if ($canViewSensitive)
                    <x-detail :label="__('Residential address')" class="sm:col-span-2">{{ $employee->residential_address }}</x-detail>
                    <x-detail :label="__('Emergency contact')">{{ $employee->emergency_contact_name }}</x-detail>
                    <x-detail :label="__('Emergency contact number')">{{ $employee->emergency_contact_number }}</x-detail>
                @endif
            </dl>
        </x-card>

        @if ($canViewSensitive)
            <x-card :title="__('Personal Information')">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <x-detail :label="__('Sex')">{{ $employee->sex->label() }}</x-detail>
                    <x-detail :label="__('Civil status')">{{ $employee->civil_status->label() }}</x-detail>
                    <x-detail :label="__('Date of birth')">{{ $employee->birth_date->format('F j, Y') }}</x-detail>
                    <x-detail :label="__('Place of birth')">{{ $employee->birth_place }}</x-detail>
                </dl>
            </x-card>

            <x-card :title="__('Government IDs')" :description="__('Stored encrypted. Only the HR office and the employee can see these numbers.')">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <x-detail :label="__('GSIS BP number')"><x-masked-value :value="$employee->gsis_number" /></x-detail>
                    <x-detail :label="__('PhilHealth number')"><x-masked-value :value="$employee->philhealth_number" /></x-detail>
                    <x-detail :label="__('Pag-IBIG MID number')"><x-masked-value :value="$employee->pagibig_number" /></x-detail>
                    <x-detail :label="__('TIN')"><x-masked-value :value="$employee->tin" /></x-detail>
                </dl>
            </x-card>
        @endif

        @if ($canManage && $employee->user)
            <x-card :title="__('Login Account')">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <x-detail :label="__('Username')"><span class="font-mono">{{ $employee->user->username }}</span></x-detail>
                    <x-detail :label="__('Account status')"><x-status-badge :active="$employee->user->is_active" /></x-detail>
                    <x-detail :label="__('Last login')">{{ $employee->user->last_login_at?->format('M j, Y g:i A') ?? __('Never') }}</x-detail>
                    <x-detail :label="__('Password')">
                        {{ $employee->user->must_change_password ? __('Temporary (not yet changed)') : __('Set by the employee') }}
                    </x-detail>
                </dl>
                <p class="mt-4 text-xs text-ink-muted">{{ __('Forgotten passwords are reset by the System Administrator under User Accounts.') }}</p>
            </x-card>
        @endif
    </div>

    @if ($canViewSensitive)
        <x-card id="documents" :title="__('201 File')" :description="__('Appointment papers, IDs, certificates and other documents.')">
            @if ($canManage)
                <form method="POST" action="{{ route('employees.documents.store', $employee) }}" enctype="multipart/form-data" class="mb-6 grid gap-4 rounded-xl border border-line bg-surface-muted p-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                    @csrf
                    <div>
                        <x-input-label for="document_type" :value="__('Document type')" />
                        <x-select-input id="document_type" name="document_type" class="mt-1" :options="$documentTypes" :selected="old('document_type')" :placeholder="__('Select type')" required />
                        <x-input-error :messages="$errors->get('document_type')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="title" :value="__('Title (optional)')" />
                        <x-text-input id="title" name="title" type="text" class="mt-1" :value="old('title')" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="file" :value="__('File (PDF, JPG or PNG, up to 10 MB)')" />
                        <input id="file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required class="mt-1 block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 dark:file:bg-brand-500/10 dark:file:text-brand-300 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:hover:file:bg-brand-500/20">
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>
                    <div>
                        <x-primary-button class="w-full">{{ __('Upload') }}</x-primary-button>
                    </div>
                </form>
            @endif

            <x-data-table :empty="__('No documents in the 201 file yet.')">
                <thead>
                    <tr>
                        <th>{{ __('Document') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Size') }}</th>
                        <th>{{ __('Uploaded') }}</th>
                        <th data-orderable="false" class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td>
                                <span class="flex items-center gap-2 font-medium text-ink">
                                    <x-icon name="document" class="size-4 shrink-0 text-ink-subtle" />
                                    {{ $document->title }}
                                </span>
                                <span class="block text-xs text-ink-muted">{{ $document->original_name }}</span>
                            </td>
                            <td>{{ $document->document_type->label() }}</td>
                            <td data-order="{{ $document->size }}">{{ \Illuminate\Support\Number::fileSize($document->size, precision: 1) }}</td>
                            <td data-order="{{ $document->created_at->timestamp }}">
                                {{ $document->created_at->format('M j, Y') }}
                                @if ($document->uploader)
                                    <span class="block text-xs text-ink-muted">{{ __('by :name', ['name' => $document->uploader->name]) }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @if ($document->isViewableInline())
                                        <a href="{{ route('employees.documents.show', [$employee, $document]) }}" target="_blank" rel="noopener" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('View') }}</a>
                                    @endif
                                    <a href="{{ route('employees.documents.download', [$employee, $document]) }}" class="text-sm font-medium text-brand-700 dark:text-brand-400 hover:text-brand-900 dark:hover:text-brand-300">{{ __('Download') }}</a>
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('employees.documents.destroy', [$employee, $document]) }}" data-confirm="{{ __('Delete this document permanently?') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">{{ __('Delete') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
        </x-card>
    @endif
</x-app-layout>
