<?php

namespace App\Services\Backup;

use App\Enums\AuditEvent;
use App\Enums\BackupTrigger;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\SystemSettings;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Spatie\Permission\PermissionRegistrar;
use Throwable;
use ZipArchive;

/**
 * Creates, lists, restores and prunes backups. A backup is one zip archive
 * holding the database dump (database.sql), the uploaded files (files/…)
 * and a manifest (manifest.json).
 *
 * Restoring first takes a safety backup of the current state and returns
 * to it automatically if the restore fails. Only one backup or restore runs
 * at a time.
 */
class BackupService
{
    private const MANIFEST = 'manifest.json';

    private const DATABASE = 'database.sql';

    private const FILES_PREFIX = 'files/';

    private const FORMAT = 1;

    public function __construct(
        private DatabaseDumper $dumper,
        private AuditLogger $audit,
        private SystemSettings $settings,
    ) {}

    /**
     * Every backup in storage, newest first. Unreadable archives are left out.
     *
     * @return Collection<int, Backup>
     */
    public function all(): Collection
    {
        return collect($this->disk()->files())
            ->filter(fn (string $name): bool => str_ends_with(strtolower($name), '.zip'))
            ->map(fn (string $name): ?Backup => $this->read($name))
            ->filter()
            ->sortByDesc(fn (Backup $backup): string => $backup->createdAt->format('YmdHis').$backup->name)
            ->values();
    }

    public function find(string $name): ?Backup
    {
        return $this->isSafeName($name) && $this->disk()->exists($name) ? $this->read($name) : null;
    }

    public function create(BackupTrigger $trigger, ?User $user = null): Backup
    {
        return $this->exclusively(function () use ($trigger, $user): Backup {
            $backup = $this->write($trigger, $user);

            if (in_array($trigger, [BackupTrigger::Manual, BackupTrigger::Scheduled], true)) {
                $this->prune($this->settings->backupsKept());
            }

            return $backup;
        });
    }

    /**
     * Replace the database and uploaded files with a backup's contents.
     */
    public function restore(Backup $backup, ?User $user = null): void
    {
        $this->exclusively(function () use ($backup, $user): void {
            $this->assertRestorable($backup);

            $safetyBackup = $this->write(BackupTrigger::BeforeRestore, $user);

            try {
                $this->restoreArchive($backup);
            } catch (Throwable $exception) {
                report($exception);

                try {
                    $this->restoreArchive($safetyBackup);
                } catch (Throwable $rollbackException) {
                    report($rollbackException);

                    throw new BackupException(__('The restore failed, and the system could not be returned to its previous state. Restore :name from the command line.', ['name' => $safetyBackup->name]), previous: $exception);
                }

                throw new BackupException(__('The restore failed. Nothing was changed.'), previous: $exception);
            }

            Artisan::call('migrate', ['--force' => true]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            // The restored database may not have the account that ran the restore.
            $actor = $user !== null && User::query()->whereKey($user->getKey())->exists() ? $user : null;

            $this->audit->record(
                AuditEvent::BackupRestored,
                __(':name restored by :user', ['name' => $backup->name, 'user' => $user?->username ?? __('the command line')]),
                actor: $actor,
                newValues: ['safety_backup' => $safetyBackup->name],
            );
        });
    }

    /**
     * Keep a backup made elsewhere (e.g. copied from another server).
     */
    public function upload(UploadedFile $file, User $user): Backup
    {
        $manifest = $this->manifestOf($file->getRealPath());

        if ($manifest === null) {
            throw new BackupException(__('This file is not a backup of this system.'));
        }

        $name = $this->newName(BackupTrigger::Uploaded);

        if ($this->disk()->putFileAs('', $file, $name) === false) {
            throw new BackupException(__('The uploaded backup could not be saved.'));
        }

        $backup = $this->read($name) ?? throw new BackupException(__('The uploaded backup could not be saved.'));
        $this->audit->record(AuditEvent::BackupUploaded, $name, actor: $user, newValues: ['created_at' => $backup->createdAt->toDateTimeString(), 'created_by' => $backup->createdBy]);

        return $backup;
    }

    public function delete(Backup $backup, ?User $user = null): void
    {
        $this->disk()->delete($backup->name);
        $this->audit->record(AuditEvent::BackupDeleted, $backup->name, actor: $user);
    }

    /**
     * Delete all but the newest backups.
     */
    public function prune(int $keep): void
    {
        $this->all()->slice($keep)->each(fn (Backup $backup) => $this->delete($backup));
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk(config('backup.disk'));
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function exclusively(callable $callback): mixed
    {
        $lock = Cache::lock('backups', 3600);

        if (! $lock->get()) {
            throw new BackupException(__('Another backup or restore is in progress. Try again in a few minutes.'));
        }

        set_time_limit(0);

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function write(BackupTrigger $trigger, ?User $user): Backup
    {
        $name = $this->newName($trigger);
        $archivePath = tempnam(sys_get_temp_dir(), 'hris-archive-');
        $sqlPath = tempnam(sys_get_temp_dir(), 'hris-backup-');
        $filesDisk = $this->filesDisk();
        $temporaryCopies = [];

        try {
            $this->dumper->dump($sqlPath);

            $zip = new ZipArchive;

            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new BackupException(__('The backup file could not be created. Check the free disk space.'));
            }

            $zip->addFile($sqlPath, self::DATABASE);
            $files = $this->backedUpFiles($filesDisk);

            foreach ($files as $file) {
                $zip->addFile($this->localCopyOf($filesDisk, $file, $temporaryCopies), self::FILES_PREFIX.$file);
            }

            $zip->addFromString(self::MANIFEST, json_encode([
                'application' => config('app.name'),
                'format' => self::FORMAT,
                'created_at' => now()->toIso8601String(),
                'created_by' => $user?->name,
                'trigger' => $trigger->value,
                'migrations' => DB::table('migrations')->orderBy('id')->pluck('migration')->all(),
                'file_count' => count($files),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            if (! $zip->close()) {
                throw new BackupException(__('The backup file could not be written. Check the free disk space.'));
            }

            $this->store($name, $archivePath);
        } finally {
            @unlink($sqlPath);
            @unlink($archivePath);
            array_map('unlink', array_filter($temporaryCopies, 'file_exists'));
        }

        $backup = $this->read($name) ?? throw new BackupException(__('The backup file could not be read back.'));
        $this->audit->record(AuditEvent::BackupCreated, $name, actor: $user, newValues: ['trigger' => $trigger->label(), 'size' => $backup->readableSize()]);

        return $backup;
    }

    /**
     * A path the zip can read: the file itself on a local disk, otherwise a
     * temporary copy (removed once the archive is written).
     *
     * @param  list<string>  $temporaryCopies
     */
    private function localCopyOf(FilesystemAdapter $disk, string $file, array &$temporaryCopies): string
    {
        if ($this->isLocal($disk)) {
            return $disk->path($file);
        }

        $copy = tempnam(sys_get_temp_dir(), 'hris-file-');
        $temporaryCopies[] = $copy;
        file_put_contents($copy, $disk->readStream($file));

        return $copy;
    }

    /**
     * Save a finished archive to the backups disk.
     */
    private function store(string $name, string $archivePath): void
    {
        $stream = fopen($archivePath, 'rb');

        try {
            $isStored = $this->disk()->writeStream($name, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $isStored) {
            throw new BackupException(__('The backup file could not be saved to the backup storage.'));
        }
    }

    /**
     * Run the callback with a temporary local copy of the archive, which zip
     * needs and a bucket cannot give. The copy is removed afterwards.
     *
     * @template TResult
     *
     * @param  callable(string): TResult  $callback
     * @return TResult
     */
    private function withLocalArchive(string $name, callable $callback): mixed
    {
        $stream = $this->disk()->readStream($name) ?? throw new BackupException(__('The backup file could not be opened.'));
        $path = tempnam(sys_get_temp_dir(), 'hris-download-');

        try {
            file_put_contents($path, $stream);

            return $callback($path);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            @unlink($path);
        }
    }

    private function restoreArchive(Backup $backup): void
    {
        $this->withLocalArchive($backup->name, function (string $archivePath): void {
            $zip = $this->open($archivePath);
            $sqlPath = tempnam(sys_get_temp_dir(), 'hris-restore-');

            try {
                $in = $zip->getStream(self::DATABASE);
                $out = fopen($sqlPath, 'wb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);

                $this->dumper->restore($sqlPath);
                $this->restoreFiles($zip);
            } finally {
                $zip->close();
                @unlink($sqlPath);
            }
        });
    }

    /**
     * Replace the uploaded files with the archive's.
     */
    private function restoreFiles(ZipArchive $zip): void
    {
        $filesDisk = $this->filesDisk();

        foreach ($filesDisk->directories() as $directory) {
            if ($directory !== config('backup.bucket_folder')) {
                $filesDisk->deleteDirectory($directory);
            }
        }

        $filesDisk->delete($filesDisk->files());

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = (string) $zip->getNameIndex($index);

            if (! str_starts_with($entry, self::FILES_PREFIX) || str_ends_with($entry, '/')) {
                continue;
            }

            $relativePath = substr($entry, strlen(self::FILES_PREFIX));

            if (! $this->isSafeRelativePath($relativePath)) {
                continue;
            }

            $stream = $zip->getStream($entry);
            $filesDisk->writeStream($relativePath, $stream);
            fclose($stream);
        }
    }

    /**
     * Refuse archives without a database dump, and backups from a newer
     * version of the system (their tables would not match this code).
     */
    private function assertRestorable(Backup $backup): void
    {
        $hasDatabase = $this->withLocalArchive($backup->name, function (string $archivePath): bool {
            $zip = $this->open($archivePath);
            $hasDatabase = $zip->locateName(self::DATABASE) !== false;
            $zip->close();

            return $hasDatabase;
        });

        if (! $hasDatabase) {
            throw new BackupException(__('This backup has no database in it.'));
        }

        $known = array_keys(app('migrator')->getMigrationFiles(database_path('migrations')));
        $unknown = array_diff($backup->migrations, $known);

        if ($unknown !== []) {
            throw new BackupException(__('This backup was made by a newer version of the system. Update the system before restoring it.'));
        }
    }

    /**
     * A backup described by its manifest. Manifests are cached, so listing
     * the backups does not copy every archive each time.
     */
    private function read(string $name): ?Backup
    {
        $size = (int) $this->disk()->size($name);

        try {
            $manifest = Cache::rememberForever(
                "backup-manifest:{$name}:{$size}",
                fn (): ?array => $this->withLocalArchive($name, $this->manifestOf(...)),
            );
        } catch (BackupException) {
            return null;
        }

        if ($manifest === null) {
            return null;
        }

        return new Backup(
            name: $name,
            size: $size,
            createdAt: CarbonImmutable::parse($manifest['created_at'])->setTimezone(config('app.timezone')),
            createdBy: $manifest['created_by'] ?? null,
            trigger: BackupTrigger::tryFrom((string) ($manifest['trigger'] ?? '')),
            migrations: array_values(array_map('strval', $manifest['migrations'] ?? [])),
            fileCount: (int) ($manifest['file_count'] ?? 0),
        );
    }

    /**
     * The manifest of an archive made by this system, or null.
     *
     * @return array<string, mixed>|null
     */
    private function manifestOf(string $path): ?array
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return null;
        }

        $manifest = json_decode((string) $zip->getFromName(self::MANIFEST), true);
        $zip->close();

        $isValid = is_array($manifest)
            && ($manifest['application'] ?? null) === config('app.name')
            && ($manifest['format'] ?? null) === self::FORMAT
            && is_string($manifest['created_at'] ?? null)
            && strtotime($manifest['created_at']) !== false;

        return $isValid ? $manifest : null;
    }

    private function open(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new BackupException(__('The backup file could not be opened.'));
        }

        return $zip;
    }

    private function newName(BackupTrigger $trigger): string
    {
        $base = 'kagjanaw-hris-'.now()->format('Y-m-d-His').'-'.$trigger->value;
        $name = $base.'.zip';

        for ($suffix = 2; $this->disk()->exists($name); $suffix++) {
            $name = $base.'-'.$suffix.'.zip';
        }

        return $name;
    }

    /**
     * Archive names are plain file names: no folders, no parent references.
     */
    private function isSafeName(string $name): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.zip\z/', $name) === 1;
    }

    private function isSafeRelativePath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '\\')
            && ! str_contains($path, ':')
            && ! in_array('..', explode('/', $path), true)
            && ! str_starts_with($path, config('backup.bucket_folder').'/');
    }

    private function filesDisk(): FilesystemAdapter
    {
        return Storage::disk(config('backup.files_disk'));
    }

    /**
     * Every uploaded file, without the backups themselves when they share the
     * uploads bucket.
     *
     * @return list<string>
     */
    private function backedUpFiles(FilesystemAdapter $filesDisk): array
    {
        $backupsFolder = config('backup.bucket_folder').'/';

        return array_values(array_filter(
            $filesDisk->allFiles(),
            fn (string $file): bool => ! str_starts_with($file, $backupsFolder),
        ));
    }

    private function isLocal(FilesystemAdapter $disk): bool
    {
        return $disk->getAdapter() instanceof LocalFilesystemAdapter;
    }
}
