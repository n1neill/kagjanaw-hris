<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

beforeEach(function () {
    // Independent of whether `npm run dev` happens to be running on this computer.
    $this->hotFile = storage_path('framework/testing/vite-'.Str::random(8).'.hot');
    Vite::useHotFile($this->hotFile);
});

afterEach(function () {
    File::delete($this->hotFile);
});

it('sends the security headers and a content security policy with pages', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertHeaderMissing('Strict-Transport-Security');
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("script-src 'self' 'unsafe-eval'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->not->toContain("script-src 'self' 'unsafe-eval' 'unsafe-inline'");
});

it('leaves the content security policy off downloads, which open in the browser\'s own viewer', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();

    $response = $this->actingAs($hrOfficer)->get(route('attendance.import.template'));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Content-Security-Policy');
});

it('leaves the content security policy off while the Vite dev server runs, whose IPv6 address a policy cannot name', function () {
    File::ensureDirectoryExists(dirname($this->hotFile));
    File::put($this->hotFile, 'http://[::1]:5173');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('http://[::1]:5173/resources/css/app.css', false)
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeaderMissing('Content-Security-Policy');
});

it('tells browsers to keep using HTTPS once the site is served over HTTPS', function () {
    $this->get('https://localhost/login')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('pages have no inline scripts or event handlers, which the content security policy blocks', function () {
    $offending = collect(File::allFiles(resource_path('views')))
        // PDFs are rendered by DomPDF, never by a browser.
        ->reject(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '-pdf.blade.php'))
        ->filter(fn (SplFileInfo $file): bool => preg_match('/<script(?![^>]*\bsrc=)|\son[a-z]+\s*=\s*["\']/i', $file->getContents()) === 1)
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offending)->toBe([]);
});
