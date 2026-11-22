<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserPermissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User $account */
        $account = $this->route('user');

        return $this->user()->can('changePermissions', $account);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only HR administration permissions may be granted on top of a role.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(array_map(
                fn (Permission $permission): string => $permission->value,
                Permission::grantable(),
            ))],
        ];
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return array_map(
            fn (string $value): Permission => Permission::from($value),
            array_values($this->validated('permissions') ?? []),
        );
    }
}
