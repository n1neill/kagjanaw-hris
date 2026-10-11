<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Bootstraps the first System Administrator on a fresh production install,
 * where the demo accounts are not seeded. The password is always prompted
 * so it never appears in shell history.
 */
#[Signature('app:create-administrator {--name= : Full name of the administrator} {--username= : Username used to sign in} {--email= : Optional email address for password resets}')]
#[Description('Create a System Administrator account')]
class CreateAdministrator extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(UserService $users): int
    {
        $data = [
            'name' => $this->option('name') ?? $this->ask('Full name'),
            'username' => $this->option('username') ?? $this->ask('Username'),
            'email' => $this->option('email'),
            'password' => $this->secret('Password (at least 12 characters with upper and lower case letters, a number and a symbol)'),
        ];
        $data['password_confirmation'] = $this->secret('Confirm password');

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique(User::class)],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = $users->create([
            ...$validator->safe()->only(['name', 'username', 'email', 'password']),
            'role' => Role::SystemAdministrator->value,
        ], mustChangePassword: false);

        $this->info("System Administrator account \"{$user->username}\" created.");

        return self::SUCCESS;
    }
}
