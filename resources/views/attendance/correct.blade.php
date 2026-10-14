<x-app-layout :title="__('Correct DTR')">
    <x-slot name="header">{{ __('Correct Daily Time Record') }}</x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" :title="$employee->full_name.' — '.$day->format('l, F j, Y')"
                :description="__('Enter the correct punches in 24-hour time. Leave a punch blank if there was none. The day\'s late, undertime and absence are recomputed, and the change is recorded with your name and reason.')">
            @if ($record->exists)
                <p class="mb-4 text-sm text-ink-muted">
                    {{ __('Current status:') }} <x-badge :color="$record->status->badgeColor()">{{ $record->status->label() }}</x-badge>
                    @if ($record->validated_at)
                        <span class="ml-2 text-xs text-ink-muted">{{ __('Validated — correcting it will require validating the month again.') }}</span>
                    @endif
                </p>
            @endif

            <form method="POST" action="{{ route('attendance.dtr.update', [$employee, $day->toDateString()]) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-4">
                    @foreach (['am_in' => __('Morning in'), 'am_out' => __('Morning out'), 'pm_in' => __('Afternoon in'), 'pm_out' => __('Afternoon out')] as $field => $label)
                        <div>
                            <x-input-label :for="$field" :value="$label" />
                            <x-text-input :id="$field" :name="$field" type="time" class="mt-1" :value="old($field, $punches[$field])" />
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </div>
                    @endforeach
                </div>

                <div>
                    <x-input-label for="reason" :value="__('Reason for the correction')" />
                    <x-textarea-input id="reason" name="reason" class="mt-1" required placeholder="{{ __('e.g. Forgot to punch out; confirmed by the department head (memo dated Oct 5).') }}">{{ old('reason') }}</x-textarea-input>
                    <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>{{ __('Save Correction') }}</x-primary-button>
                    <x-button-link :href="route('attendance.dtr.show', [$employee, 'month' => $day->format('Y-m')])" variant="secondary">{{ __('Cancel') }}</x-button-link>
                </div>
            </form>
        </x-card>

        <div class="space-y-6">
            <x-card :title="__('Punches recorded')" :description="__('Raw logs from the device or CSV import for this day.')">
                @forelse ($rawLogs as $log)
                    <p class="flex items-center justify-between border-b border-line py-1.5 text-sm last:border-0">
                        <span class="font-mono">{{ $log->logged_at->format('H:i:s') }}</span>
                        <span class="text-xs text-ink-muted">{{ $log->source->label() }}</span>
                    </p>
                @empty
                    <p class="text-sm text-ink-muted">{{ __('No punches were recorded on this day.') }}</p>
                @endforelse
            </x-card>

            <x-card :title="__('Correction history')">
                @forelse ($adjustments as $adjustment)
                    <div class="border-b border-line py-2 text-sm last:border-0">
                        <p class="font-medium text-ink">{{ $adjustment->user?->name ?? __('Unknown user') }}
                            <span class="font-normal text-ink-muted">· {{ $adjustment->created_at->format('M j, Y g:i A') }}</span>
                        </p>
                        <ul class="mt-1 text-xs text-ink-muted">
                            @foreach ($adjustment->changes as $field => $change)
                                <li>{{ str_replace('_', ' ', $field) }}: <span class="font-mono">{{ $change['from'] ?? '—' }} → {{ $change['to'] ?? '—' }}</span></li>
                            @endforeach
                        </ul>
                        <p class="mt-1 text-ink">{{ $adjustment->reason }}</p>
                    </div>
                @empty
                    <p class="text-sm text-ink-muted">{{ __('This day has not been corrected.') }}</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-app-layout>
