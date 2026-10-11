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
     * The name and username of an employee's account follow the employee
     * record, so they are prohibited here for linked accounts.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $account = $this->account();
        $canChangeRole = $this->user()->can('changeRole', $account);
        $isEmployeeAccount = $account->employee()->exists();

        return [
            'name' => $isEmployeeAccount
                ? ['prohibited']
                : ['required', 'string', 'max:255'],
            'username' => $isEmployeeAccount
                ? ['prohibited']
                : ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique(User::class)->ignore($account)],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($account)],
            'role' => $canChangeRole
                ? ['required', Rule::enum(Role::class)]
                : ['prohibited'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => __('The username may only contain letters, numbers, dots, dashes and underscores.'),
        ];
    }

    private function account(): User
    {
        return $this->route('user');
    }
}
