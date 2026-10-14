<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemSettingsRequest;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * System settings the administrator maintains (sync interval, backups,
 * audit log retention).
 */
class SystemSettingsController extends Controller
{
    public function __construct(private SystemSettings $settings) {}

    public function edit(): View
    {
        return view('admin.settings', [
            'syncIntervalMinutes' => $this->settings->syncIntervalMinutes(),
            'backupEnabled' => $this->settings->isBackupEnabled(),
            'backupTime' => $this->settings->backupTime(),
            'backupsKept' => $this->settings->backupsKept(),
            'auditRetentionYears' => $this->settings->auditRetentionYears(),
        ]);
    }

    public function update(SystemSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->settings(), $request->user());

        return redirect()->route('admin.settings.edit')->with('status', __('System settings saved.'));
    }
}
