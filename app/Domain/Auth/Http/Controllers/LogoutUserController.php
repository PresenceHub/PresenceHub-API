<?php

namespace App\Domain\Auth\Http\Controllers;

use App\Domain\Auth\Http\Requests\LogoutUserRequest;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUserController
{
    public function __invoke(LogoutUserRequest $request): Response
    {
        $current = $request->user()?->currentAccessToken();

        if ($current instanceof PersonalAccessToken) {
            $current->delete();
        } else {
            PersonalAccessToken::findToken($request->bearerToken() ?? '')?->delete();
        }

        return response()->noContent();
    }
}
