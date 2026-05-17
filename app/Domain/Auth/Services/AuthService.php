<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DTOs\LoginUserDataDTO;
use App\Domain\Auth\DTOs\RegisterUserDataDTO;
use App\Domain\Auth\Events\UserRegistered;
use App\Enums\Role as RoleSlug;
use App\Enums\WorkspaceMemberRole;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string}  $validated
     */
    public function registerUser(array $validated): User
    {
        $data = RegisterUserDataDTO::fromArray($validated);

        $creatorRole = Role::findBySlug(RoleSlug::CUSTOMER->value);
        if ($creatorRole === null) {
            throw new Exception('Customer role not found');
        }

        $user = new User([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
        ]);

        $user->role()->associate($creatorRole);
        $user->save();

        $workspace = Workspace::create([
            'name' => $data->name,
            'owner_id' => $user->id,
        ]);

        $workspace->members()->attach($user, ['role' => WorkspaceMemberRole::Owner]);

        $user = $user->loadMissing('role');

        Event::dispatch(new UserRegistered($user, $workspace));
        Event::dispatch(new Registered($user));

        return $user;
    }

    /**
     * @param  array{email: string, password: string}  $validated
     */
    public function loginUser(array $validated): ?User
    {
        $data = LoginUserDataDTO::fromArray($validated);

        if (! Auth::attempt([
            'email' => $data->email,
            'password' => $data->password,
        ])) {
            return null;
        }

        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
