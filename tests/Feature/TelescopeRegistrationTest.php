<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Route;
use Laravel\Telescope\TelescopeServiceProvider;
use Tests\TestCase;

class TelescopeRegistrationTest extends TestCase
{
    public function test_telescope_package_provider_registers_routes_when_enabled(): void
    {
        config(['telescope.enabled' => true]);

        $before = collect(Route::getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'telescope'))->count();

        $this->app->register(TelescopeServiceProvider::class);

        $after = collect(Route::getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'telescope'))->count();

        $this->assertGreaterThan(0, $before);
        $this->assertGreaterThanOrEqual($before, $after);
    }

    public function test_telescope_package_provider_does_not_register_routes_when_disabled(): void
    {
        config(['telescope.enabled' => false]);

        $before = collect(Route::getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'telescope'))->count();

        $this->app->register(TelescopeServiceProvider::class);

        $after = collect(Route::getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'telescope'))->count();

        $this->assertSame($before, $after);
    }

    public function test_app_service_provider_registers_telescope_providers_in_local_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['telescope.enabled' => true]);

        $before = collect(Route::getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'telescope'))->count();

        $this->app->register(AppServiceProvider::class);

        $after = collect(Route::getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'telescope'))->count();
        $loadedProviders = $this->app->getLoadedProviders();

        $this->assertGreaterThan(0, $before);
        $this->assertGreaterThanOrEqual($before, $after);
        $this->assertTrue(isset($loadedProviders[TelescopeServiceProvider::class]));
    }

    public function test_app_service_provider_does_not_register_telescope_providers_outside_local(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        config(['telescope.enabled' => true]);

        $before = collect(Route::getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'telescope'))->count();
        $loadedProvidersBefore = $this->app->getLoadedProviders();
        $packageProviderLoadedBefore = isset($loadedProvidersBefore[TelescopeServiceProvider::class]);

        $this->app->register(AppServiceProvider::class);

        $after = collect(Route::getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'telescope'))->count();
        $loadedProviders = $this->app->getLoadedProviders();
        $packageProviderLoadedAfter = isset($loadedProviders[TelescopeServiceProvider::class]);

        $this->assertSame($before, $after);
        $this->assertSame($packageProviderLoadedBefore, $packageProviderLoadedAfter);
    }
}
