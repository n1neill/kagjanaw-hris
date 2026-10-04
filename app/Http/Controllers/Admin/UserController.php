<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('admin.users.index', ['users' => $users]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', ['roles' => Role::options()]);
    }

    public function store(StoreUserRequest $request, UserService $users): RedirectResponse
    {
        $user = $users->create($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('Account for :name created.', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::options(),
            'currentRole' => $user->primaryRole(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UserService $users): RedirectResponse
    {
        $users->update($user, $request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('Account for :name updated.', ['name' => $user->name]));
    }
}
