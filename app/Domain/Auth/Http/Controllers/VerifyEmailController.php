<?php

namespace App\Domain\Auth\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailController
{
    public function __invoke(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::query()->find($id);

        if ($user === null) {
            return response()->json([
                'message' => 'Invalid verification link.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Invalid verification link.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email address is already verified.',
                'isEmailVerified' => true,
            ]);
        }

        if ($user->markEmailAsVerified()) {
            Event::dispatch(new Verified($user));
        }

        return response()->json([
            'message' => 'Email address verified successfully.',
            'isEmailVerified' => true,
        ]);
    }
}
