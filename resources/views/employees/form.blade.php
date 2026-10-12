@php
    $isNew = ! $employee->exists;
    $positionGrades = $positions->pluck('salary_grade', 'id');
@endphp

<x-app-layout :title="$isNew ? __('New Employee') : __('Edit Employee')">
    <x-slot name="header">{{ $isNew ? __('New Employee') : __('Edit Employee') }}</x-slot>

    <form method="POST" action="{{ $isNew ? route('employees.store') : route('employees.update', $employee) }}" class="space-y-6"
          x-data="{
              grades: @js($positionGrades),
              status: @js(old('employment_status', $employee->employment_status?->value)),
              fillGrade(event) {
                  const grade = this.grades[event.target.value];
                  if (grade && ! this.$refs.salaryGrade.value) {
                      this.$refs.salaryGrade.value = grade;
                  }
              },
          }">
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        @if ($errors->any())
            <div role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ __('Please correct the highlighted fields below.') }}
            </div>
        @endif

        <x-card :title="__('Personal Information')">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-input-label for="last_name" :value="__('Last name')" />
                    <x-text-input id="last_name" name="last_name" class="mt-1" :value="old('last_name', $employee->last_name)" required autofocus />
                    <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="first_name" :value="__('First name')" />
                    <x-text-input id="first_name" name="first_name" class="mt-1" :value="old('first_name', $employee->first_name)" required />
                    <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="middle_name" :value="__('Middle name')" />
                    <x-text-input id="middle_name" name="middle_name" class="mt-1" :value="old('middle_name', $employee->middle_name)" />
                    <x-input-error :messages="$errors->get('middle_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="name_extension" :value="__('Extension (Jr., III)')" />
                    <x-text-input id="name_extension" name="name_extension" class="mt-1" maxlength="10" :value="old('name_extension', $employee->name_extension)" />
                    <x-input-error :messages="$errors->get('name_extension')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="sex" :value="__('Sex')" />
                    <x-select-input id="sex" name="sex" class="mt-1" :options="$sexes" :selected="old('sex', $employee->sex?->value)" :placeholder="__('Select')" required />
                    <x-input-error :messages="$errors->get('sex')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="civil_status" :value="__('Civil status')" />
                    <x-select-input id="civil_status" name="civil_status" class="mt-1" :options="$civilStatuses" :selected="old('civil_status', $employee->civil_status?->value)" :placeholder="__('Select')" required />
                    <x-input-error :messages="$errors->get('civil_status')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="birth_date" :value="__('Date of birth')" />
                    <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1" :value="old('birth_date', $employee->birth_date?->toDateString())" required />
                    <x-input-error :messages="$errors->get('birth_date')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="birth_place" :value="__('Place of birth')" />
                    <x-text-input id="birth_place" name="birth_place" class="mt-1" :value="old('birth_place', $employee->birth_place)" />
                    <x-input-error :messages="$errors->get('birth_place')" class="mt-2" />
                </div>
            </div>
        </x-card>

        <x-card :title="__('Contact')">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <x-input-label for="mobile_number" :value="__('Mobile number')" />
                    <x-text-input id="mobile_number" name="mobile_number" type="tel" class="mt-1" placeholder="09171234567" :value="old('mobile_number', $employee->mobile_number)" />
                    <x-input-error :messages="$errors->get('mobile_number')" class="mt-2" />
                </div>
                <div class="lg:col-span-2">
                    <x-input-label for="email" :value="__('Email (optional, for password resets)')" />
                    <x-text-input id="email" name="email" type="email" class="mt-1" :value="old('email', $employee->user?->email)" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <x-input-label for="residential_address" :value="__('Residential address')" />
                    <x-text-input id="residential_address" name="residential_address" class="mt-1" :value="old('residential_address', $employee->residential_address)" />
                    <x-input-error :messages="$errors->get('residential_address')" class="mt-2" />
                </div>
                <div class="lg:col-span-2">
                    <x-input-label for="emergency_contact_name" :value="__('Emergency contact person')" />
                    <x-text-input id="emergency_contact_name" name="emergency_contact_name" class="mt-1" :value="old('emergency_contact_name', $employee->emergency_contact_name)" />
                    <x-input-error :messages="$errors->get('emergency_contact_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="emergency_contact_number" :value="__('Emergency contact number')" />
                    <x-text-input id="emergency_contact_number" name="emergency_contact_number" type="tel" class="mt-1" :value="old('emergency_contact_number', $employee->emergency_contact_number)" />
                    <x-input-error :messages="$errors->get('emergency_contact_number')" class="mt-2" />
                </div>
            </div>
        </x-card>

        <x-card :title="__('Employment')">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <x-input-label for="employee_number" :value="__('Employee ID')" />
                    <x-text-input id="employee_number" name="employee_number" class="mt-1 font-mono uppercase" maxlength="30" placeholder="2026-0001" :value="old('employee_number', $employee->employee_number)" required aria-describedby="employee-number-hint" />
                    <p id="employee-number-hint" class="mt-1 text-xs text-gray-500">{{ __('Also the employee\'s username for signing in.') }}</p>
                    <x-input-error :messages="$errors->get('employee_number')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="department_id" :value="__('Department')" />
                    <x-select-input id="department_id" name="department_id" class="mt-1" :options="$departments" :selected="old('department_id', $employee->department_id)" :placeholder="__('Select department')" required />
                    <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="position_id" :value="__('Position')" />
                    <x-select-input id="position_id" name="position_id" class="mt-1" :options="$positions->pluck('title', 'id')" :selected="old('position_id', $employee->position_id)" :placeholder="__('Select position')" required x-on:change="fillGrade($event)" />
                    <x-input-error :messages="$errors->get('position_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="employment_type_id" :value="__('Employment type')" />
                    <x-select-input id="employment_type_id" name="employment_type_id" class="mt-1" :options="$employmentTypes" :selected="old('employment_type_id', $employee->employment_type_id)" :placeholder="__('Select type')" required />
                    <x-input-error :messages="$errors->get('employment_type_id')" class="mt-2" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="salary_grade" :value="__('Salary grade')" />
                        <x-text-input id="salary_grade" name="salary_grade" type="number" min="1" max="33" class="mt-1" x-ref="salaryGrade" :value="old('salary_grade', $employee->salary_grade)" />
                        <x-input-error :messages="$errors->get('salary_grade')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="salary_step" :value="__('Step')" />
                        <x-text-input id="salary_step" name="salary_step" type="number" min="1" max="8" class="mt-1" :value="old('salary_step', $employee->salary_step)" />
                        <x-input-error :messages="$errors->get('salary_step')" class="mt-2" />
                    </div>
                </div>
                <div>
                    <x-input-label for="daily_rate" :value="__('Daily rate (Job Order / COS)')" />
                    <x-text-input id="daily_rate" name="daily_rate" inputmode="decimal" class="mt-1" :value="old('daily_rate', $employee->daily_rate)" aria-describedby="daily-rate-hint" />
                    <p id="daily-rate-hint" class="mt-1 text-xs text-gray-500">{{ __('Only for workers paid by the day.') }}</p>
                    <x-input-error :messages="$errors->get('daily_rate')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="date_hired" :value="__('Date hired')" />
                    <x-text-input id="date_hired" name="date_hired" type="date" class="mt-1" :value="old('date_hired', $employee->date_hired?->toDateString())" required />
                    <x-input-error :messages="$errors->get('date_hired')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="employment_status" :value="__('Employment status')" />
                    <x-select-input id="employment_status" name="employment_status" class="mt-1" :options="$employmentStatuses" :selected="old('employment_status', $employee->employment_status?->value)" required x-model="status" />
                    <x-input-error :messages="$errors->get('employment_status')" class="mt-2" />
                </div>
                <div x-show="status !== 'active'" x-cloak>
                    <x-input-label for="separation_date" :value="__('Separation date')" />
                    <x-text-input id="separation_date" name="separation_date" type="date" class="mt-1" :value="old('separation_date', $employee->separation_date?->toDateString())" x-bind:disabled="status === 'active'" />
                    <p class="mt-1 text-xs text-gray-500">{{ __('Separated employees can no longer sign in.') }}</p>
                    <x-input-error :messages="$errors->get('separation_date')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="biometric_user_id" :value="__('Biometric user ID')" />
                    <x-text-input id="biometric_user_id" name="biometric_user_id" inputmode="numeric" class="mt-1" :value="old('biometric_user_id', $employee->biometric_user_id)" aria-describedby="biometric-hint" />
                    <p id="biometric-hint" class="mt-1 text-xs text-gray-500">{{ __('The user number enrolled on the ZKTeco device.') }}</p>
                    <x-input-error :messages="$errors->get('biometric_user_id')" class="mt-2" />
                </div>
            </div>
        </x-card>

        <x-card :title="__('Government IDs')" :description="__('Stored encrypted. Dashes and spaces are removed automatically.')">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-input-label for="gsis_number" :value="__('GSIS BP number')" />
                    <x-text-input id="gsis_number" name="gsis_number" class="mt-1 font-mono" autocomplete="off" :value="old('gsis_number', $employee->gsis_number)" />
                    <x-input-error :messages="$errors->get('gsis_number')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="philhealth_number" :value="__('PhilHealth number')" />
                    <x-text-input id="philhealth_number" name="philhealth_number" class="mt-1 font-mono" autocomplete="off" placeholder="12-345678901-2" :value="old('philhealth_number', $employee->philhealth_number)" />
                    <x-input-error :messages="$errors->get('philhealth_number')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="pagibig_number" :value="__('Pag-IBIG MID number')" />
                    <x-text-input id="pagibig_number" name="pagibig_number" class="mt-1 font-mono" autocomplete="off" placeholder="1234-5678-9012" :value="old('pagibig_number', $employee->pagibig_number)" />
                    <x-input-error :messages="$errors->get('pagibig_number')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="tin" :value="__('TIN')" />
                    <x-text-input id="tin" name="tin" class="mt-1 font-mono" autocomplete="off" placeholder="123-456-789-000" :value="old('tin', $employee->tin)" />
                    <x-input-error :messages="$errors->get('tin')" class="mt-2" />
                </div>
            </div>
        </x-card>

        <div class="flex flex-wrap items-center gap-3">
            <x-primary-button>{{ $isNew ? __('Add Employee and Create Account') : __('Save Changes') }}</x-primary-button>
            <x-button-link :href="$isNew ? route('employees.index') : route('employees.show', $employee)" variant="secondary">{{ __('Cancel') }}</x-button-link>
        </div>
    </form>
</x-app-layout>
