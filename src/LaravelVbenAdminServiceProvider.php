<?php

namespace Chencongbao\LaravelVbenAdmin;

use Chencongbao\LaravelVbenAdmin\Console\CreateAdminCommand;
use Chencongbao\LaravelVbenAdmin\Console\InstallCommand;
use Chencongbao\LaravelVbenAdmin\Console\SyncSystemDataCommand;
use Chencongbao\LaravelVbenAdmin\Contracts\Authorizer;
use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Contracts\ModuleRegistry;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\EnsureAdminUser;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\RequirePermission;
use Chencongbao\LaravelVbenAdmin\Services\DatabaseAuthorizer;
use Chencongbao\LaravelVbenAdmin\Services\DatabaseAuditRecorder;
use Chencongbao\LaravelVbenAdmin\Services\InMemoryModuleRegistry;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class LaravelVbenAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-vben-admin.php', 'laravel-vben-admin');

        $this->app->singleton(ModuleRegistry::class, InMemoryModuleRegistry::class);
        $this->app->singleton(Authorizer::class, DatabaseAuthorizer::class);
        $this->app->singleton(AuditRecorder::class, DatabaseAuditRecorder::class);
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $router->aliasMiddleware('admin.user', EnsureAdminUser::class);
        $router->aliasMiddleware('admin.permission', RequirePermission::class);
        Route::middleware(config('laravel-vben-admin.route.middleware', ['api']))
            ->prefix(config('laravel-vben-admin.route.prefix', 'api/admin'))
            ->group(__DIR__.'/../routes/admin.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-vben-admin.php' => config_path('laravel-vben-admin.php'),
            ], 'laravel-vben-admin-config');

            $this->commands([
                InstallCommand::class,
                CreateAdminCommand::class,
                SyncSystemDataCommand::class,
            ]);
        }
    }
}
