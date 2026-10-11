<x-app-layout :title="__('Office Hours')">
    <x-slot name="header">{{ __('Office Hours') }}</x-slot>

    <x-card class="max-w-3xl" :title="__('Regular office hours')"
            :description="__('Used to compute late arrivals, undertime and absences on every Daily Time Record. Changes apply to attendance processed from now on; HR can recompute earlier months from each DTR.')">
        <form method="POST" action="{{ route('work-calendar.schedule.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">{{ __('Sessions') }}</legend>
                <div class="mt-2 grid gap-4 sm:grid-cols-4">
                    @foreach (['am_start' => __('Morning start'), 'am_end' => __('Morning end'), 'pm_start' => __('Afternoon start'), 'pm_end' => __('Afternoon end')] as $field => $label)
                        <div>
                            <x-input-label :for="$field" :value="$label" />
                            <x-text-input :id="$field" :name="$field" type="time" class="mt-1" :value="old($field, substr($schedule->{$field}, 0, 5))" required />
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="max-w-xs">
                <x-input-label for="grace_minutes" :value="__('Grace period (minutes)')" />
                <x-text-input id="grace_minutes" name="grace_minutes" type="number" min="0" max="60" class="mt-1" :value="old('grace_minutes', $schedule->grace_minutes)" required aria-describedby="grace-hint" />
                <p id="grace-hint" class="mt-1 text-xs text-gray-500">{{ __('Arrivals within this many minutes of the start of a session are on time. Later arrivals are late from the start of the session.') }}</p>
                <x-input-error :messages="$errors->get('grace_minutes')" class="mt-2" />
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">{{ __('Working days') }}</legend>
                <div class="mt-2 flex flex-wrap gap-4">
                    @php($selectedDays = array_map('intval', old('working_days', $schedule->working_days)))
                    @foreach ($dayNames as $number => $name)
                        <x-checkbox name="working_days[]" :value="$number" :checked="in_array($number, $selectedDays, true)" :label="__($name)" />
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('working_days')" class="mt-2" />
            </fieldset>

            <x-primary-button>{{ __('Save Office Hours') }}</x-primary-button>
        </form>
    </x-card>
</x-app-layout>
