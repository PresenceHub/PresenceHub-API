<?php

namespace App\Domain\Auth\Http\Controllers;

use App\Domain\Auth\Events\UserLoggedOut;
use App\Domain\Auth\Http\Requests\LogoutUserRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUserController
{
    public function __invoke(LogoutUserRequest $request): Response
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        if ($current instanceof PersonalAccessToken) {
            $current->delete();
        } else {
            PersonalAccessToken::findToken($request->bearerToken() ?? '')?->delete();
        }

        Event::dispatch(new UserLoggedOut($user));

        return response()->noContent();
    }
}
