<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementAudience;
use App\Enums\Permission;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An announcement, saved as a draft or published.
 */
class AnnouncementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageAnnouncements);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_pinned' => $this->boolean('is_pinned'),
            'expires_on' => $this->filled('expires_on') ? $this->input('expires_on') : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'departments' => ['exclude_unless:audience,'.AnnouncementAudience::Departments->value, 'required', 'array', 'min:1'],
            'departments.*' => ['integer', 'distinct', Rule::exists(Department::class, 'id')],
            'is_pinned' => ['required', 'boolean'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'action' => ['required', Rule::in(['publish', 'draft'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'departments.required' => __('Choose at least one office.'),
            'expires_on.after_or_equal' => __('The expiry date cannot be in the past.'),
        ];
    }

    public function shouldPublish(): bool
    {
        return $this->validated('action') === 'publish';
    }

    /**
     * @return array{title: string, body: string, audience: AnnouncementAudience, is_pinned: bool, expires_on: ?string, departments: list<int>}
     */
    public function announcement(): array
    {
        $audience = AnnouncementAudience::from($this->validated('audience'));

        return [
            'title' => $this->validated('title'),
            'body' => $this->validated('body'),
            'audience' => $audience,
            'is_pinned' => (bool) $this->validated('is_pinned'),
            'expires_on' => $this->validated('expires_on'),
            'departments' => $audience === AnnouncementAudience::Departments ? array_map('intval', $this->validated('departments')) : [],
        ];
    }
}
