<?php

namespace App\Providers;

use App\Domain\Auth\Events\UserRegistered;
use App\Domain\Auth\Listeners\SendRegistrationWelcomeEmail;
use App\Domain\Content\Models\Post;
use App\Events\Contracts\ShouldBeRecorded;
use App\Listeners\RecordEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\PostPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider as TelescopePackageServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(TelescopePackageServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Post::class, PostPolicy::class);

        Route::macro('enum', function (string $enum, callable $callback) {
            foreach ($enum::cases() as $case) {
                $callback($case);
            }
        });

        Event::listen(ShouldBeRecorded::class, RecordEvent::class);
        Event::listen(UserRegistered::class, SendRegistrationWelcomeEmail::class);

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $webUrl = rtrim((string) config('app.ph_web_url'), '/');

            return $webUrl.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->email,
            ]);
        });

        VerifyEmail::createUrlUsing(function (User $user): string {
            $webUrl = rtrim((string) config('app.ph_web_url'), '/');

            $apiSignedUrl = URL::temporarySignedRoute(
                'v1.auth.email.verify',
                now()->addMinutes(60),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
                absolute: false,
            );

            $parsed = parse_url($apiSignedUrl);
            parse_str($parsed['query'] ?? '', $query);

            return $webUrl.'/verify-email?'.http_build_query([
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
                'expires' => $query['expires'] ?? '',
                'signature' => $query['signature'] ?? '',
            ]);
        });
    }
}
