<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BiometricDeviceRequest;
use App\Models\BiometricDevice;
use App\Services\Attendance\BiometricDriverManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Biometric device settings and their synchronization history.
 */
class BiometricDeviceController extends Controller
{
    public function index(): View
    {
        return view('admin.devices.index', [
            'devices' => BiometricDevice::query()->orderBy('name')->get(),
            'syncInterval' => (int) config('attendance.sync_interval_minutes'),
        ]);
    }

    public function create(): View
    {
        return view('admin.devices.form', [
            'device' => new BiometricDevice([
                'driver' => config('attendance.default_driver'),
                'port' => 4370,
                'is_active' => true,
            ]),
            'drivers' => BiometricDriverManager::options(),
        ]);
    }

    public function store(BiometricDeviceRequest $request): RedirectResponse
    {
        $device = BiometricDevice::create($request->validated());

        return redirect()
            ->route('admin.devices.show', $device)
            ->with('status', __(':name added. Test the connection to make sure it can be reached.', ['name' => $device->name]));
    }

    /**
     * The device's settings, status and sync log.
     */
    public function show(BiometricDevice $device): View
    {
        return view('admin.devices.show', [
            'device' => $device,
            'runs' => $device->syncRuns()->with('triggeredBy')->latest('started_at')->latest('id')->paginate(25),
        ]);
    }

    public function edit(BiometricDevice $device): View
    {
        return view('admin.devices.form', [
            'device' => $device,
            'drivers' => BiometricDriverManager::options(),
        ]);
    }

    public function update(BiometricDeviceRequest $request, BiometricDevice $device): RedirectResponse
    {
        $device->update($request->validated());

        return redirect()
            ->route('admin.devices.show', $device)
            ->with('status', __(':name updated.', ['name' => $device->name]));
    }
}
