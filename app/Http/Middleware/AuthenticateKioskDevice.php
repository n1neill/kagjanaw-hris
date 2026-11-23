<?php

namespace App\Http\Middleware;

use App\Models\BiometricDevice;
use App\Services\Attendance\Kiosk\KioskTokenService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admits kiosk API requests signed with a kiosk's token (Authorization:
 * Bearer). The kiosk must be active and, when it has a registered IP address,
 * call from it.
 * Each admitted request counts as the kiosk checking in.
 */
class AuthenticateKioskDevice
{
    public const DEVICE_ATTRIBUTE = 'kioskDevice';

    public function __construct(private KioskTokenService $tokens) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $device = $this->tokens->findDevice((string) $request->bearerToken());

        abort_if($device === null, 401, __('The kiosk token is missing or no longer valid.'));
        abort_unless($device->is_active && $device->receivesPushedPunches(), 403, __('This kiosk is disabled in the HRIS.'));
        abort_unless($device->ip_address === null || $request->ip() === $device->ip_address, 403, __('This kiosk token is not accepted from this computer\'s address. The HRIS sees this kiosk at :address, but the kiosk is registered for :registered.', [
            'address' => $request->ip(),
            'registered' => $device->ip_address,
        ]));

        $seenAt = CarbonImmutable::now();
        BiometricDevice::query()->whereKey($device->id)->update(['last_seen_at' => $seenAt]);
        $device->last_seen_at = $seenAt;
        $device->syncOriginalAttribute('last_seen_at');

        $request->attributes->set(self::DEVICE_ATTRIBUTE, $device);

        return $next($request);
    }

    /**
     * The kiosk the current request was admitted for.
     */
    public static function device(Request $request): BiometricDevice
    {
        return $request->attributes->get(self::DEVICE_ATTRIBUTE);
    }
}
