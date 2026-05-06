<?php

namespace App\Domain\Auth\Http\Controllers;

use App\Domain\Auth\Events\UserLoggedIn;
use App\Domain\Auth\Http\Requests\LoginUserRequest;
use App\Domain\Auth\Http\Resources\UserResource;
use App\Domain\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;

class LoginUserController
{
    public function __construct(private AuthService $authService) {}

    public function __invoke(LoginUserRequest $request): JsonResponse
    {
        $user = $this->authService->loginUser($request->validated());

        if ($user === null) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        $user->loadMissing('role', 'currentWorkspace', 'workspaces');
        $token = $user->createToken('api')->plainTextToken;

        Event::dispatch(new UserLoggedIn($user));

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
        ]);
    }
}
