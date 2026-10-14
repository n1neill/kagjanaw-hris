<?php

namespace App\Http\Requests;

use App\Enums\ThemePreference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Every signed-in user may choose their own colour scheme.
 */
class ThemeUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'theme' => ['required', Rule::enum(ThemePreference::class)],
        ];
    }
}
