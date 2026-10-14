<x-app-layout :title="__('Backups')">
    <x-slot name="header">{{ __('Backups') }}</x-slot>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Backups kept')" :value="$backups->count()" icon="server" :hint="__('The newest :count are kept.', ['count' => $backupsKept])" />
        <x-stat-card :label="__('Latest backup')" :value="$backups->first()?->createdAt->diffForHumans() ?? __('None yet')" :hint="$backups->first()?->createdAt->format('M j, Y g:i A')" />
        <x-stat-card :label="__('Daily backup')" :value="$isScheduled ? \Carbon\Carbon::createFromFormat('H:i', $backupTime)->format('g:i A') : __('Off')" :hint="__('Change it in System Settings.')" />
    </div>

    <x-card :title="__('Backups')" :description="__('Each backup holds the whole database and every uploaded file. Copy backups to a USB drive or another computer regularly: a backup on this server does not survive a disk failure.')">
        <x-slot name="actions">
            <form method="POST" action="{{ route('admin.backups.store') }}">
                @csrf
                <x-primary-button>{{ __('Back Up Now') }}</x-primary-button>
            </form>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="py-2 pr-3">{{ __('Created') }}</th>
                        <th class="px-2 py-2">{{ __('Type') }}</th>
                        <th class="px-2 py-2">{{ __('By') }}</th>
                        <th class="px-2 py-2 text-right">{{ __('Files') }}</th>
                        <th class="px-2 py-2 text-right">{{ __('Size') }}</th>
                        <th class="px-2 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($backups as $index => $backup)
                        <tr class="border-b border-gray-100">
                            <td class="py-2 pr-3">
                                <span class="block font-medium text-gray-900">{{ $backup->createdAt->format('M j, Y g:i A') }}</span>
                                <span class="block font-mono text-xs text-gray-500">{{ $backup->name }}</span>
                            </td>
                            <td class="px-2 py-2">{{ $backup->trigger?->label() ?? '—' }}</td>
                            <td class="px-2 py-2">{{ $backup->createdBy ?? __('System') }}</td>
                            <td class="px-2 py-2 text-right tabular-nums">{{ $backup->fileCount }}</td>
                            <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $backup->readableSize() }}</td>
                            <td class="px-2 py-2">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-button-link :href="route('admin.backups.download', $backup->name)" variant="secondary">{{ __('Download') }}</x-button-link>
                                    <x-secondary-button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'restore-{{ $index }}')">{{ __('Restore') }}</x-secondary-button>
                                    <form method="POST" action="{{ route('admin.backups.destroy', $backup->name) }}" data-confirm="{{ __('Delete this backup? This cannot be undone.') }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-danger-button>{{ __('Delete') }}</x-danger-button>
                                    </form>
                                </div>

                                <x-modal :name="'restore-'.$index" :show="$errors->restore->isNotEmpty() && old('backup_name') === $backup->name" focusable>
                                    <form method="POST" action="{{ route('admin.backups.restore', $backup->name) }}" class="p-6">
                                        @csrf
                                        <input type="hidden" name="backup_name" value="{{ $backup->name }}">

                                        <h2 class="text-lg font-semibold text-gray-900">{{ __('Restore the backup of :date?', ['date' => $backup->createdAt->format('M j, Y g:i A')]) }}</h2>
                                        <p class="mt-2 text-sm text-gray-600">
                                            {{ __('Every record and uploaded file will be replaced with the backup\'s contents. Anything entered after the backup was made will be lost. A safety backup of the current data is made first, and the system returns to it automatically if the restore fails.') }}
                                        </p>
                                        <p class="mt-2 text-sm text-gray-600">{{ __('Ask everyone to stop using the system until the restore is finished.') }}</p>

                                        <div class="mt-4">
                                            <x-input-label :for="'password-'.$index" :value="__('Your password')" />
                                            <x-text-input :id="'password-'.$index" name="password" type="password" class="mt-1" autocomplete="current-password" required />
                                            <x-input-error :messages="$errors->restore->get('password')" class="mt-2" />
                                        </div>

                                        <div class="mt-6 flex justify-end gap-3">
                                            <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                                            <x-danger-button>{{ __('Restore') }}</x-danger-button>
                                        </div>
                                    </form>
                                </x-modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-500">{{ __('No backups yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card :title="__('Upload a backup')" :description="__('To restore a backup kept elsewhere, upload it here first. Only backups made by this system are accepted.')">
        <form method="POST" action="{{ route('admin.backups.upload') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-0 flex-1">
                <x-input-label for="backup" :value="__('Backup file (.zip, up to :size MB)', ['size' => $maxUploadMegabytes])" />
                <input id="backup" name="backup" type="file" accept=".zip,application/zip" required class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-gray-200">
                <x-input-error :messages="$errors->get('backup')" class="mt-2" />
            </div>
            <x-secondary-button type="submit">{{ __('Upload') }}</x-secondary-button>
        </form>
        <p class="mt-3 text-xs text-gray-500">{{ __('If the upload is refused because the file is too large, copy the file into the storage\app\backups folder on the server instead; it then appears in the list.') }}</p>
    </x-card>
</x-app-layout>
