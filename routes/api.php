<?php

use App\Domain\Auth\Http\Controllers\ForgotPasswordController;
use App\Domain\Auth\Http\Controllers\LoginUserController;
use App\Domain\Auth\Http\Controllers\LogoutUserController;
use App\Domain\Auth\Http\Controllers\RegisterUserController;
use App\Domain\Auth\Http\Controllers\ResendEmailVerificationController;
use App\Domain\Auth\Http\Controllers\ResetPasswordController;
use App\Domain\Auth\Http\Controllers\VerifyEmailController;
use App\Domain\Content\Http\Controllers\ChannelController;
use App\Domain\Content\Http\Controllers\PlatformController;
use App\Domain\Content\Http\Controllers\PostController;
use App\Domain\Content\Http\Controllers\PostMediaController;
use App\Domain\Content\Http\Controllers\SocialPlatformOAuthController;
use App\Enums\Platform;
use App\Facades\Route;

// Quick ping/test route
Route::get('/ping', fn () => ['message' => 'pong']);

Route::prefix('v1')->name('v1.')->group(function () {
    // Auth routes
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', RegisterUserController::class)
            ->middleware('throttle:10,1')
            ->name('register');

        Route::post('/login', LoginUserController::class)
            ->middleware('throttle:5,1')
            ->name('login');

        Route::post('/forgot-password', ForgotPasswordController::class)
            ->middleware('throttle:5,1')
            ->name('forgot-password');

        Route::post('/reset-password', ResetPasswordController::class)
            ->middleware('throttle:5,1')
            ->name('reset-password');

        Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware('signed:relative')
            ->name('email.verify');

        Route::post('/logout', LogoutUserController::class)
            ->middleware('auth:sanctum')
            ->name('logout');

        Route::post('/email/verification-notification', ResendEmailVerificationController::class)
            ->middleware(['auth:sanctum', 'throttle:6,1'])
            ->name('email.verification-notification');
    });

    Route::enum(Platform::class, function ($case) {
        Route::get("/social/{$case->value}/callback", [SocialPlatformOAuthController::class, 'callback'])
            ->defaults('platform', $case->value)
            ->name("social.{$case->value}.callback");
    });

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('platforms', [PlatformController::class, 'index'])->name('platforms.index');

        Route::prefix('channels')->name('channels.')->group(function () {
            Route::get('/', [ChannelController::class, 'index'])->name('index');
            Route::post('/', [ChannelController::class, 'connect'])->name('connect');
            Route::delete('/{channel}', [ChannelController::class, 'disconnect'])->name('disconnect');
        });

        Route::enum(Platform::class, function (Platform $case) {
            Route::prefix("social/{$case->value}")->name("social.{$case->value}.")->group(function () use ($case) {
                Route::get('/connect', [SocialPlatformOAuthController::class, 'connect'])
                    ->defaults('platform', $case->value)
                    ->name('connect');

                Route::post('/channels', [SocialPlatformOAuthController::class, 'connectChannels'])
                    ->defaults('platform', $case->value)
                    ->name('channels.connect');
            });
        });

        Route::prefix('posts/media')->name('media.')->group(function () {
            Route::post('/upload', [PostMediaController::class, 'upload'])->name('upload');
            Route::post('/attach', [PostMediaController::class, 'attach'])->name('attach');
            Route::delete('/{media}', [PostMediaController::class, 'delete'])->name('delete');
        });

        Route::prefix('posts')->name('posts.')->group(function () {
            Route::get('/', [PostController::class, 'index'])->name('index');
            Route::post('/', [PostController::class, 'store'])->name('store');
            Route::get('/{post}', [PostController::class, 'show'])->name('show');
            Route::patch('/{post}', [PostController::class, 'update'])->name('update');
            Route::delete('/{post}', [PostController::class, 'destroy'])->name('destroy');
        });
    });
});
