@use('App\Services\Leave\LeaveDays')

@php
    $typeInfo = $types->mapWithKeys(fn ($type) => [$type->id => [
        'description' => $type->description,
        'halfDay' => $type->allows_half_day,
        'notice' => $type->advance_notice_days,
        'attachment' => $type->attachment_hint,
        'source' => $type->credit_source->label(),
        'calendar' => $type->counts_calendar_days,
    ]]);
@endphp

<x-app-layout :title="__('Apply for Leave')">
    <x-slot name="header">{{ __('Apply for Leave') }}</x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" :title="__('Application for Leave')" :description="__('CS Form No. 6. Your application goes to your department head (or the Municipal Mayor) for endorsement, then to HR for approval.')">
            <form method="POST" action="{{ route('leave.requests.store') }}" enctype="multipart/form-data" class="space-y-5"
                  x-data="{
                      types: @js($typeInfo),
                      type: @js((string) old('leave_type_id', '')),
                      from: @js(old('date_from', '')),
                      to: @js(old('date_to', '')),
                      get info() { return this.types[this.type] ?? null },
                      get singleDay() { return this.from !== '' && (this.to === '' || this.to === this.from) },
                  }">
                @csrf

                @if ($errors->any())
                    <div role="alert" class="rounded-lg border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                        {{ __('Please correct the highlighted fields below.') }}
                    </div>
                @endif

                <div>
                    <x-input-label for="leave_type_id" :value="__('Type of leave')" />
                    <x-select-input id="leave_type_id" name="leave_type_id" class="mt-1" :options="$types->pluck('name', 'id')" :selected="old('leave_type_id')" :placeholder="__('Select the type of leave')" required x-model="type" />
                    <x-input-error :messages="$errors->get('leave_type_id')" class="mt-2" />

                    <template x-if="info">
                        <div class="mt-2 rounded-lg bg-surface-muted p-3 text-xs text-ink-muted">
                            <p x-text="info.description"></p>
                            <p class="mt-1 font-medium text-ink">
                                <span x-text="'{{ __('Charged to:') }} ' + info.source"></span>
                                <span x-show="info.calendar">· {{ __('counted in calendar days') }}</span>
                                <span x-show="info.notice > 0" x-text="'· {{ __('file at least') }} ' + info.notice + ' {{ __('days ahead') }}'"></span>
                            </p>
                        </div>
                    </template>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="date_from" :value="__('First day')" />
                        <x-text-input id="date_from" name="date_from" type="date" class="mt-1" :value="old('date_from')" required x-model="from" />
                        <x-input-error :messages="$errors->get('date_from')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="date_to" :value="__('Last day')" />
                        <x-text-input id="date_to" name="date_to" type="date" class="mt-1" :value="old('date_to')" x-model="to" x-bind:min="from" />
                        <p class="mt-1 text-xs text-ink-muted">{{ __('Leave blank for a single day. Rest days and holidays are not counted.') }}</p>
                        <x-input-error :messages="$errors->get('date_to')" class="mt-2" />
                    </div>
                </div>

                <div x-show="singleDay && (info === null || info.halfDay)" x-cloak>
                    <x-input-label for="portion" :value="__('Duration')" />
                    <x-select-input id="portion" name="portion" class="mt-1 sm:w-64" :options="$portions" :selected="old('portion', 'full')" />
                    <x-input-error :messages="$errors->get('portion')" class="mt-2" />
                </div>
                <template x-if="! singleDay || (info !== null && ! info.halfDay)">
                    <input type="hidden" name="portion" value="full">
                </template>

                <div>
                    <x-input-label for="details" :value="__('Details of leave')" />
                    <x-textarea-input id="details" name="details" class="mt-1" rows="3" required placeholder="{{ __('e.g. Vacation within the Philippines; In hospital (specify illness); Completion of master\'s degree') }}">{{ old('details') }}</x-textarea-input>
                    <x-input-error :messages="$errors->get('details')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="attachment" :value="__('Supporting document (PDF, JPG or PNG, up to 5 MB)')" />
                    <input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="mt-1 block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 dark:file:bg-brand-500/10 dark:file:text-brand-300 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:hover:file:bg-brand-500/20">
                    <p class="mt-1 text-xs text-ink-muted" x-show="info && info.attachment" x-text="info ? info.attachment : ''"></p>
                    <x-input-error :messages="$errors->get('attachment')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>{{ __('File Application') }}</x-primary-button>
                    <x-button-link :href="route('leave.my')" variant="secondary">{{ __('Cancel') }}</x-button-link>
                </div>
            </form>
        </x-card>

        <x-card :title="__('Your balances')">
            @include('leave.partials.balances')
            <p class="mt-4 text-xs text-ink-muted">{{ __('If your credits are not enough, the excess days are leave without pay.') }}</p>
        </x-card>
    </div>
</x-app-layout>
