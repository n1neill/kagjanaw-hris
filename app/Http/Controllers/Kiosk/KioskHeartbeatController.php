<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateKioskDevice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The kiosk checks in (recorded by the middleware) and learns the server's
 * time, so it can warn when its own clock has drifted.
 */
class KioskHeartbeatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $device = AuthenticateKioskDevice::device($request);

        return response()->json([
            'device' => ['id' => $device->id, 'name' => $device->name, 'location' => $device->location],
            'server_time' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }
}
