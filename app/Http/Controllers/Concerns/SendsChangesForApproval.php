<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

/**
 * The response when a change made through a granted permission was queued
 * for HR Officer approval instead of being saved.
 */
trait SendsChangesForApproval
{
    protected function sentForApproval(string $url): RedirectResponse
    {
        return redirect()
            ->to($url)
            ->with('status', __('Your change was sent to an HR Officer for approval. It will be recorded once approved.'));
    }
}
