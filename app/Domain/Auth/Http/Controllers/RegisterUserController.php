<?php

namespace App\Domain\Auth\Http\Controllers;

use App\Domain\Auth\Http\Requests\RegisterUserRequest;
use App\Domain\Auth\Http\Resources\UserResource;
use App\Domain\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;

class RegisterUserController
{
    public function __construct(private AuthService $authService) {}

    public function __invoke(RegisterUserRequest $request): JsonResponse
    {
        $user = $this->authService->registerUser($request->validated());
        
        $user->loadMissing('role', 'currentWorkspace', 'workspaces');
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
        ], 201);
    }
}
