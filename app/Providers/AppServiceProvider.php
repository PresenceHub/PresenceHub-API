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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
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
    }
}
