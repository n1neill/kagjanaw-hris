<?php

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\BiometricDevice;
use Carbon\CarbonImmutable;

it('refuses a request without a valid kiosk token', function (?string $token) {
    BiometricDevice::factory()->kiosk('the-real-token')->create();

    $request = $token === null ? $this : $this->withToken($token);

    $request->getJson(route('kiosk.heartbeat'))
        ->assertUnauthorized()
        ->assertJson(['message' => 'The kiosk token is missing or no longer valid.']);
})->with([
    'no token' => [null],
    'wrong token' => ['a-guessed-token'],
]);

it('refuses a disabled kiosk', function () {
    BiometricDevice::factory()->kiosk()->inactive()->create();

    $this->withToken('test-kiosk-token')->getJson(route('kiosk.heartbeat'))
        ->assertForbidden()
        ->assertJson(['message' => 'This kiosk is disabled in the HRIS.']);
});

it('refuses the token of a device that does not push punches', function () {
    BiometricDevice::factory()->kiosk()->create(['driver' => 'mock']);

    $this->withToken('test-kiosk-token')->getJson(route('kiosk.heartbeat'))->assertForbidden();
});

it('refuses the token from another computer and audits it', function () {
    BiometricDevice::factory()->kiosk(ipAddress: '192.168.1.50')->create();

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.77'])
        ->withToken('test-kiosk-token')
        ->getJson(route('kiosk.heartbeat'))
        ->assertForbidden()
        ->assertJson(['message' => 'This kiosk token is not accepted from this computer\'s address.']);

    expect(AuditLog::query()->where('event', AuditEvent::AccessDenied)->count())->toBe(1);
});

it('records the check-in and tells the kiosk the server time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 07:45:10'));
    $device = BiometricDevice::factory()->kiosk()->create();

    $this->withToken('test-kiosk-token')->getJson(route('kiosk.heartbeat'))
        ->assertOk()
        ->assertJson([
            'device' => ['id' => $device->id, 'name' => 'Lobby Fingerprint Kiosk'],
            'server_time' => '2026-11-06T07:45:10+08:00',
        ]);

    expect($device->refresh()->last_seen_at->toDateTimeString())->toBe('2026-11-06 07:45:10')
        ->and(AuditLog::query()->where('event', AuditEvent::Updated)->count())->toBe(0);
});
