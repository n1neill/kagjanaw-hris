<x-app-layout :title="__('Edit Deduction')">
    <x-slot name="header">{{ __('Edit Deduction') }}</x-slot>

    <x-card class="max-w-4xl" :title="$deduction->name" :description="$employee->full_name">
        <form method="POST" action="{{ route('payroll.employees.deductions.update', [$employee, $deduction]) }}" class="space-y-5">
            @csrf
            @method('PUT')

            @include('payroll.employees.partials.deduction-fields', ['deduction' => $deduction])

            <div>
                <input type="hidden" name="is_active" value="0">
                <x-checkbox name="is_active" value="1" :checked="old('is_active', $deduction->is_active)" :label="__('Active (untick to stop deducting)')" />
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                <x-button-link :href="route('payroll.employees.show', $employee)" variant="secondary">{{ __('Cancel') }}</x-button-link>
            </div>
        </form>
    </x-card>
</x-app-layout>
