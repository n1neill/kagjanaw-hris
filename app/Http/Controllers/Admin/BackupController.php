<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Enums\BackupTrigger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestoreBackupRequest;
use App\Http\Requests\Admin\UploadBackupRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupException;
use App\Services\Backup\BackupService;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Database and file backups: create, upload, download, restore and delete.
 */
class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index(SystemSettings $settings): View
    {
        return view('admin.backups.index', [
            'backups' => $this->backups->all(),
            'isScheduled' => $settings->isBackupEnabled(),
            'backupTime' => $settings->backupTime(),
            'backupsKept' => $settings->backupsKept(),
            'maxUploadMegabytes' => config('backup.max_upload_mb'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backups->create(BackupTrigger::Manual, $request->user());
        } catch (BackupException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.backups.index')->with('status', __('Backup :name created.', ['name' => $backup->name]));
    }

    public function upload(UploadBackupRequest $request): RedirectResponse
    {
        try {
            $backup = $this->backups->upload($request->file('backup'), $request->user());
        } catch (BackupException $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        return redirect()->route('admin.backups.index')->with('status', __('Backup uploaded as :name. It can now be restored.', ['name' => $backup->name]));
    }

    public function download(string $backup, AuditLogger $audit): StreamedResponse
    {
        $found = $this->findOrFail($backup);

        $audit->record(AuditEvent::BackupDownloaded, $found->name);

        return $this->backups->disk()->download($found->name, $found->name, ['Cache-Control' => 'private, no-store, max-age=0']);
    }

    public function restore(RestoreBackupRequest $request, string $backup): RedirectResponse
    {
        $found = $this->findOrFail($backup);

        try {
            $this->backups->restore($found, $request->user());
        } catch (BackupException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.backups.index')->with('status', __('The system was restored to :date. A safety backup of the data before the restore was kept.', ['date' => $found->createdAt->format('M j, Y g:i A')]));
    }

    public function destroy(Request $request, string $backup): RedirectResponse
    {
        $found = $this->findOrFail($backup);

        $this->backups->delete($found, $request->user());

        return redirect()->route('admin.backups.index')->with('status', __('Backup :name deleted.', ['name' => $found->name]));
    }

    private function findOrFail(string $name): Backup
    {
        $backup = $this->backups->find($name);

        abort_if($backup === null, 404);

        return $backup;
    }
}
