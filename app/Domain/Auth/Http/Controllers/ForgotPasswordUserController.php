<?php

namespace App\Domain\Auth\Http\Controllers;

use App\Domain\Auth\Http\Requests\ForgotPasswordUserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordUserController
{
    public function __invoke(ForgotPasswordUserRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => __($status),
            ], 429);
        }

        return response()->json([
            'message' => 'If that email address is in our system, we have emailed a password reset link.',
        ]);
    }
}
