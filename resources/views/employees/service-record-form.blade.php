{{-- Add or correct a service record entry; a new $entry carries the suggested values. --}}
@php($isNew = ! $entry->exists)

<x-app-layout :title="$isNew ? __('Add Service Record Entry') : __('Edit Service Record Entry')">
    <x-slot name="header">{{ $isNew ? __('Add Service Record Entry') : __('Edit Service Record Entry') }}</x-slot>

    <x-card class="max-w-4xl" :title="$employee->full_name" :description="$isNew
        ? __('Filled in from the employee\'s current appointment. Change anything that differs for this period.')
        : __('Employee ID :number', ['number' => $employee->employee_number])">
        <form method="POST" action="{{ $isNew ? route('employees.service-record.store', $employee) : route('employees.service-record.update', [$employee, $entry]) }}" class="space-y-5">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            <div class="grid gap-4 sm:grid-cols-4" x-data="{ separated: @js(filled(old('separation_date', $entry->separation_date?->toDateString()))) }">
                <div>
                    <x-input-label for="service_from" :value="__('Service from')" />
                    <x-text-input id="service_from" name="service_from" type="date" class="mt-1" :value="old('service_from', $entry->service_from?->toDateString())" required />
                    <x-input-error :messages="$errors->get('service_from')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="service_to" :value="__('Service to (blank = present)')" />
                    <x-text-input id="service_to" name="service_to" type="date" class="mt-1 read-only:bg-surface-muted read-only:text-ink-muted" :value="old('service_to', $entry->service_to?->toDateString())" x-ref="serviceTo" x-bind:readonly="separated" />
                    <p x-show="separated" x-cloak class="mt-1 text-xs text-ink-muted">{{ __('Set to the separation date.') }}</p>
                    <x-input-error :messages="$errors->get('service_to')" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="designation" :value="__('Designation')" />
                    <x-text-input id="designation" name="designation" class="mt-1" :value="old('designation', $entry->designation)" required />
                    <x-input-error :messages="$errors->get('designation')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="status" :value="__('Status of appointment')" />
                    <x-text-input id="status" name="status" class="mt-1" placeholder="Permanent" :value="old('status', $entry->status)" required />
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="annual_salary" :value="__('Annual salary (₱)')" />
                    <x-text-input id="annual_salary" name="annual_salary" inputmode="decimal" class="mt-1" :value="old('annual_salary', $entry->annual_salary)" />
                    <x-input-error :messages="$errors->get('annual_salary')" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="station" :value="__('Station / place of assignment')" />
                    <x-text-input id="station" name="station" class="mt-1" :value="old('station', $entry->station)" />
                    <x-input-error :messages="$errors->get('station')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="branch" :value="__('Branch')" />
                    <x-text-input id="branch" name="branch" class="mt-1" placeholder="Local" :value="old('branch', $entry->branch ?? 'Local')" required />
                    <x-input-error :messages="$errors->get('branch')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="leave_without_pay" :value="__('Leave without pay (optional)')" />
                    <x-text-input id="leave_without_pay" name="leave_without_pay" class="mt-1" :value="old('leave_without_pay', $entry->leave_without_pay)" />
                    <x-input-error :messages="$errors->get('leave_without_pay')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="separation_date" :value="__('Separation date (optional)')" />
                    <x-text-input id="separation_date" name="separation_date" type="date" class="mt-1" :value="old('separation_date', $entry->separation_date?->toDateString())"
                        x-on:input="separated = $event.target.value !== ''; if (separated) { $refs.serviceTo.value = $event.target.value }" />
                    <x-input-error :messages="$errors->get('separation_date')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="separation_cause" :value="__('Cause of separation')" />
                    <x-text-input id="separation_cause" name="separation_cause" class="mt-1" placeholder="Resigned" :value="old('separation_cause', $entry->separation_cause)" />
                    <x-input-error :messages="$errors->get('separation_cause')" class="mt-2" />
                </div>
                <div class="sm:col-span-4">
                    <x-input-label for="remarks" :value="__('Remarks (optional)')" />
                    <x-text-input id="remarks" name="remarks" class="mt-1" :value="old('remarks', $entry->remarks)" />
                    <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>{{ $isNew ? __('Add Entry') : __('Save Changes') }}</x-primary-button>
                <x-button-link :href="route('employees.service-record.show', $employee)" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
