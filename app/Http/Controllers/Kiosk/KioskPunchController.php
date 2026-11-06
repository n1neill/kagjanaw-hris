<?php

namespace App\Http\Controllers\Kiosk;

use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateKioskDevice;
use App\Http\Requests\Kiosk\StorePunchesRequest;
use App\Services\Attendance\AttendanceSyncService;
use Illuminate\Http\JsonResponse;

/**
 * Punches matched at a kiosk. They go through the same storage as pulled
 * device logs (duplicate skipping, unknown-ID flagging, DTR refresh). The
 * kiosk keeps a batch queued until this answers 200.
 */
class KioskPunchController extends Controller
{
    public function __invoke(StorePunchesRequest $request, AttendanceSyncService $sync): JsonResponse
    {
        $run = $sync->receive(AuthenticateKioskDevice::device($request), $request->rawPunches());

        if ($run->status === SyncStatus::Failed) {
            return response()->json(['message' => __('The punches could not be saved. The kiosk will try again.')], 503);
        }

        return response()->json([
            'received' => $run->fetched_count,
            'inserted' => $run->inserted_count,
            'duplicates' => $run->duplicate_count,
            'flagged' => $run->flagged_count,
        ]);
    }
}
