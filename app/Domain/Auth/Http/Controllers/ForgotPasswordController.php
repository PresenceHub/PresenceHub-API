<?php

namespace App\Domain\Auth\Http\Controllers;

use App\Domain\Auth\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class ForgotPasswordController
{
    private const GENERIC_SUCCESS_MESSAGE = 'We have sent a email with a password reset link.';

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email'),
        );

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => $status,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return response()->json([
            'message' => self::GENERIC_SUCCESS_MESSAGE,
        ]);
    }
}
