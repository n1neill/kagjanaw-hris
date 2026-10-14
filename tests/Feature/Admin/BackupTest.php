<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Backup\DatabaseDumper;
use App\Services\SystemSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;

/**
 * Write a backup archive like the ones the system makes.
 *
 * @param  array<string, string>  $files  Path on the uploads disk => contents.
 * @param  array<string, mixed>  $manifest  Manifest overrides.
 */
function makeBackupArchive(string $path, array $files = [], array $manifest = [], ?string $database = "-- backup\n"): void
{
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    if ($database !== null) {
        $zip->addFromString('database.sql', $database);
    }

    foreach ($files as $file => $contents) {
        $zip->addFromString('files/'.$file, $contents);
    }

    $zip->addFromString('manifest.json', json_encode([
        'application' => config('app.name'),
        'format' => 1,
        'created_at' => '2026-10-01T18:00:00+08:00',
        'created_by' => 'Old Admin',
        'trigger' => 'scheduled',
        'migrations' => DB::table('migrations')->pluck('migration')->all(),
        'file_count' => count($files),
        ...$manifest,
    ]));
    $zip->close();
}

/**
 * Replace the database dumper: dumps write $dumped, restores are recorded.
 *
 * @param  list<string>  $restored  Receives the SQL of each restore.
 */
function fakeDumper(array &$restored, string $dumped = "-- current\n", ?Closure $onRestore = null): void
{
    test()->mock(DatabaseDumper::class, function (MockInterface $mock) use (&$restored, $dumped, $onRestore): void {
        $mock->shouldReceive('dump')->andReturnUsing(fn (string $path) => file_put_contents($path, $dumped));
        $mock->shouldReceive('restore')->andReturnUsing(function (string $path) use (&$restored, $onRestore): void {
            $restored[] = file_get_contents($path);

            if ($onRestore !== null) {
                $onRestore(count($restored));
            }
        });
    });
}

beforeEach(function () {
    Storage::fake('backups');
    Storage::fake('local');

    // A fresh name per test: on Windows (OneDrive, antivirus) a zip deleted
    // by the previous test can briefly block a new file of the same name.
    $this->backupName = 'old-'.Str::lower(Str::random(8)).'.zip';
});

describe('creating backups', function () {
    it('backs up the database and every uploaded file into one archive', function () {
        $restored = [];
        fakeDumper($restored);
        $admin = User::factory()->systemAdministrator()->create(['name' => 'Ana Admin']);
        Storage::disk('local')->put('employees/7/appointment.pdf', 'PDF');

        $response = $this->actingAs($admin)->post(route('admin.backups.store'));

        $response->assertRedirect(route('admin.backups.index'))->assertSessionHas('status');
        $name = Storage::disk('backups')->files()[0];
        $zip = new ZipArchive;
        $zip->open(Storage::disk('backups')->path($name));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        expect($zip->getFromName('database.sql'))->toBe("-- current\n")
            ->and($zip->getFromName('files/employees/7/appointment.pdf'))->toBe('PDF')
            ->and($manifest['created_by'])->toBe('Ana Admin')
            ->and($manifest['trigger'])->toBe('manual')
            ->and($manifest['migrations'])->toContain('2026_10_13_163905_create_audit_logs_table');
        $zip->close();

        expect(AuditLog::query()->where('event', AuditEvent::BackupCreated)->sole()->user_id)->toBe($admin->id);
    });

    it('keeps only the newest backups after the daily backup', function () {
        $restored = [];
        fakeDumper($restored);
        $admin = User::factory()->systemAdministrator()->create();
        app(SystemSettings::class)->update(['sync_interval_minutes' => 5, 'backup_enabled' => true, 'backup_time' => '18:00', 'backups_kept' => 3, 'audit_retention_years' => 5], $admin);

        foreach (['01', '02', '03', '04'] as $day) {
            makeBackupArchive(Storage::disk('backups')->path("old-{$day}.zip"), manifest: ['created_at' => "2026-10-{$day}T18:00:00+08:00"]);
        }

        $this->artisan('backup:create --scheduled')->assertSuccessful();

        $remaining = collect(Storage::disk('backups')->files())->sort()->values()->all();
        expect($remaining)->toHaveCount(3)
            ->and($remaining)->toContain('old-03.zip', 'old-04.zip')
            ->and($remaining)->not->toContain('old-01.zip', 'old-02.zip');
    });
});

describe('restoring', function () {
    it('replaces the database and uploaded files after taking a safety backup', function () {
        $restored = [];
        fakeDumper($restored);
        $admin = User::factory()->systemAdministrator()->create();
        Storage::disk('local')->put('employees/1/current.pdf', 'current');
        makeBackupArchive(Storage::disk('backups')->path($this->backupName), ['employees/1/from-backup.pdf' => 'restored']);

        $response = $this->actingAs($admin)->post(route('admin.backups.restore', $this->backupName), ['password' => 'password']);

        $response->assertRedirect(route('admin.backups.index'))->assertSessionHas('status');
        expect($restored)->toBe(["-- backup\n"])
            ->and(Storage::disk('local')->get('employees/1/from-backup.pdf'))->toBe('restored')
            ->and(Storage::disk('local')->exists('employees/1/current.pdf'))->toBeFalse();

        $safety = collect(Storage::disk('backups')->files())->first(fn (string $file): bool => str_contains($file, 'before-restore'));
        expect($safety)->not->toBeNull();

        $entry = AuditLog::query()->where('event', AuditEvent::BackupRestored)->sole();
        expect($entry->new_values)->toBe(['safety_backup' => $safety]);
    });

    it('returns to the previous state when the restore fails', function () {
        $restored = [];
        fakeDumper($restored, onRestore: function (int $call): void {
            if ($call === 1) {
                throw new RuntimeException('Lost connection');
            }
        });
        $admin = User::factory()->systemAdministrator()->create();
        Storage::disk('local')->put('employees/1/current.pdf', 'current');
        makeBackupArchive(Storage::disk('backups')->path($this->backupName), ['employees/1/from-backup.pdf' => 'restored']);

        $response = $this->actingAs($admin)->post(route('admin.backups.restore', $this->backupName), ['password' => 'password']);

        $response->assertSessionHas('error', 'The restore failed. Nothing was changed.');
        expect($restored)->toBe(["-- backup\n", "-- current\n"])
            ->and(Storage::disk('local')->get('employees/1/current.pdf'))->toBe('current')
            ->and(Storage::disk('local')->exists('employees/1/from-backup.pdf'))->toBeFalse();
    });

    it('requires the administrator\'s password', function () {
        $restored = [];
        fakeDumper($restored);
        $admin = User::factory()->systemAdministrator()->create();
        makeBackupArchive(Storage::disk('backups')->path($this->backupName));

        $response = $this->actingAs($admin)->post(route('admin.backups.restore', $this->backupName), ['password' => 'wrong-password']);

        $response->assertSessionHasErrorsIn('restore', ['password']);
        expect($restored)->toBe([]);
    });

    it('refuses a backup made by a newer version of the system', function () {
        $restored = [];
        fakeDumper($restored);
        $admin = User::factory()->systemAdministrator()->create();
        makeBackupArchive(Storage::disk('backups')->path('newer.zip'), manifest: [
            'migrations' => [...DB::table('migrations')->pluck('migration')->all(), '2027_01_01_000000_create_future_table'],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.backups.restore', 'newer.zip'), ['password' => 'password']);

        $response->assertSessionHas('error', 'This backup was made by a newer version of the system. Update the system before restoring it.');
        expect($restored)->toBe([]);
    });

    it('restores from the command line', function () {
        $restored = [];
        fakeDumper($restored);
        makeBackupArchive(Storage::disk('backups')->path($this->backupName));

        $this->artisan("backup:restore {$this->backupName} --force")->assertSuccessful();

        expect($restored)->toBe(["-- backup\n"]);
    });
});

describe('uploading, downloading and deleting', function () {
    it('accepts an uploaded backup made by this system', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $path = tempnam(sys_get_temp_dir(), 'upload');
        makeBackupArchive($path);

        $response = $this->actingAs($admin)->post(route('admin.backups.upload'), [
            'backup' => new UploadedFile($path, 'backup.zip', 'application/zip', null, true),
        ]);

        $response->assertRedirect(route('admin.backups.index'))->assertSessionHasNoErrors();
        expect(Storage::disk('backups')->files())->toHaveCount(1)
            ->and(Storage::disk('backups')->files()[0])->toContain('uploaded');
    });

    it('rejects a zip file that is not a backup of this system', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $path = tempnam(sys_get_temp_dir(), 'upload');
        makeBackupArchive($path, manifest: ['application' => 'Another System']);

        $response = $this->actingAs($admin)->post(route('admin.backups.upload'), [
            'backup' => new UploadedFile($path, 'backup.zip', 'application/zip', null, true),
        ]);

        $response->assertSessionHasErrors(['backup' => 'This file is not a backup of this system.']);
        expect(Storage::disk('backups')->files())->toBe([]);
    });

    it('downloads and deletes a backup, recording both', function () {
        $admin = User::factory()->systemAdministrator()->create();
        makeBackupArchive(Storage::disk('backups')->path($this->backupName));

        $this->actingAs($admin)->get(route('admin.backups.download', $this->backupName))->assertOk()->assertDownload($this->backupName);
        $this->actingAs($admin)->delete(route('admin.backups.destroy', $this->backupName))->assertRedirect(route('admin.backups.index'));

        expect(Storage::disk('backups')->exists($this->backupName))->toBeFalse()
            ->and(AuditLog::query()->whereIn('event', [AuditEvent::BackupDownloaded, AuditEvent::BackupDeleted])->count())->toBe(2);
    });

    it('does not serve files outside the backups folder', function () {
        $admin = User::factory()->systemAdministrator()->create();
        Storage::disk('backups')->put('notes.txt', 'not a backup');

        $this->actingAs($admin)->get('/admin/backups/..%2F..%2F.env.zip')->assertNotFound();
        $this->actingAs($admin)->get('/admin/backups/notes.txt')->assertNotFound();
    });
});

it('is only available to the System Administrator', function (Role $role) {
    $user = User::factory()->withRole($role)->create();
    makeBackupArchive(Storage::disk('backups')->path($this->backupName));

    $this->actingAs($user)->get(route('admin.backups.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.backups.store'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.backups.download', $this->backupName))->assertForbidden();
    $this->actingAs($user)->post(route('admin.backups.restore', $this->backupName), ['password' => 'password'])->assertForbidden();
})->with([
    'hr officer' => Role::HrOfficer,
    'department head' => Role::DepartmentHead,
    'employee' => Role::Employee,
]);

it('lists the backups', function () {
    $admin = User::factory()->systemAdministrator()->create();
    makeBackupArchive(Storage::disk('backups')->path($this->backupName));

    $this->actingAs($admin)->get(route('admin.backups.index'))
        ->assertOk()
        ->assertSee($this->backupName)
        ->assertSee('Old Admin');
});
