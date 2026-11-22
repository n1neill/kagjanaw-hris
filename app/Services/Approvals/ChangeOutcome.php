<?php

namespace App\Services\Approvals;

use App\Models\ChangeRequest;

/**
 * The result of ChangeRequestService::perform(): either the change was
 * applied (with the handler's result), or it was queued for approval.
 */
final readonly class ChangeOutcome
{
    private function __construct(
        public mixed $result,
        public ?ChangeRequest $changeRequest,
    ) {}

    public static function applied(mixed $result): self
    {
        return new self($result, null);
    }

    public static function queued(ChangeRequest $changeRequest): self
    {
        return new self(null, $changeRequest);
    }

    public function isQueued(): bool
    {
        return $this->changeRequest !== null;
    }
}
