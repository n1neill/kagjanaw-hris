<?php

namespace App\Http\Requests\Kiosk;

use App\Enums\VerifyType;
use App\Services\Attendance\RawPunch;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A batch of punches matched at a kiosk, oldest first. A kiosk that was
 * offline sends what it queued; re-sending a punch is harmless.
 */
class StorePunchesRequest extends FormRequest
{
    /**
     * The kiosk itself was authenticated by its token.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'punches' => ['required', 'array', 'min:1', 'max:'.(int) config('attendance.kiosk.max_punches_per_request')],
            'punches.*.biometric_user_id' => ['required', 'string', 'max:20'],
            'punches.*.logged_at' => ['required', 'date'],
            'punches.*.verify_type' => ['sometimes', Rule::enum(VerifyType::class)],
        ];
    }

    /**
     * The punches, with times converted to the office's time zone.
     *
     * @return list<RawPunch>
     */
    public function rawPunches(): array
    {
        return collect($this->validated('punches'))
            ->map(fn (array $punch): RawPunch => new RawPunch(
                $punch['biometric_user_id'],
                CarbonImmutable::parse($punch['logged_at'])->setTimezone(config('app.timezone'))->startOfSecond(),
                VerifyType::from($punch['verify_type'] ?? VerifyType::Fingerprint->value),
            ))
            ->values()
            ->all();
    }
}
