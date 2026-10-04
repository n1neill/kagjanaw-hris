<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->account());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The role is required when editing someone else and prohibited when
     * editing your own account, which prevents accidental self-demotion.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $canChangeRole = $this->user()->can('changeRole', $this->account());

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->account())],
            'role' => $canChangeRole
                ? ['required', Rule::enum(Role::class)]
                : ['prohibited'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    private function account(): User
    {
        return $this->route('user');
    }
}
