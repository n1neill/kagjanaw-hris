<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\Attendance\AttendanceSyncService;
use App\Services\Attendance\BiometricDriverManager;
use App\Services\Attendance\DeviceConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * On-demand device actions for the System Administrator.
 */
class BiometricDeviceActionController extends Controller
{
    public function testConnection(BiometricDevice $device, BiometricDriverManager $drivers): RedirectResponse
    {
        try {
            $info = $drivers->driverFor($device)->testConnection($device);
        } catch (DeviceConnectionException $exception) {
            return back()->with('error', __('Connection failed: :message', ['message' => $exception->getMessage()]));
        }

        return back()->with('status', __('Connected to :model (serial :serial, firmware :firmware): :users users enrolled, :logs logs stored.', [
            'model' => $info->model,
            'serial' => $info->serialNumber,
            'firmware' => $info->firmwareVersion,
            'users' => $info->enrolledUsers,
            'logs' => $info->storedLogs,
        ]));
    }

    public function sync(Request $request, BiometricDevice $device, AttendanceSyncService $sync): RedirectResponse
    {
        $run = $sync->sync($device, $request->user());

        if ($run->status === SyncStatus::Failed) {
            return back()->with('error', __('Sync failed: :message', ['message' => $run->error_message]));
        }

        return back()->with('status', __('Sync finished: :fetched read, :inserted new, :duplicates already stored, :flagged flagged.', [
            'fetched' => $run->fetched_count,
            'inserted' => $run->inserted_count,
            'duplicates' => $run->duplicate_count,
            'flagged' => $run->flagged_count,
        ]));
    }
}
