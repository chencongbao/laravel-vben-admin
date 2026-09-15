<?php

namespace Chencongbao\LaravelVbenAdmin;

use Chencongbao\LaravelVbenAdmin\Console\CreateAdminCommand;
use Chencongbao\LaravelVbenAdmin\Console\InstallCommand;
use Chencongbao\LaravelVbenAdmin\Console\PublishAssetsCommand;
use Chencongbao\LaravelVbenAdmin\Console\PublishWorkspaceCommand;
use Chencongbao\LaravelVbenAdmin\Console\SyncSystemDataCommand;
use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Contracts\Authorizer;
use Chencongbao\LaravelVbenAdmin\Contracts\LoginRecorder;
use Chencongbao\LaravelVbenAdmin\Contracts\ModuleRegistry;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\EnsureAdminUser;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\RequirePermission;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\RequireSuperAdmin;
use Chencongbao\LaravelVbenAdmin\Services\ActivityAuditRecorder;
use Chencongbao\LaravelVbenAdmin\Services\ActivityLoginRecorder;
use Chencongbao\LaravelVbenAdmin\Services\DatabaseAuthorizer;
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
        $this->app->scoped(AuditRecorder::class, ActivityAuditRecorder::class);
        $this->app->scoped(LoginRecorder::class, ActivityLoginRecorder::class);

        $this->app->booted(function (): void {
            config()->set(
                'activitylog.clean_after_days',
                config('laravel-vben-admin.activity_log.clean_after_days', 365),
            );
        });
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $router->aliasMiddleware('admin.user', EnsureAdminUser::class);
        $router->aliasMiddleware('admin.permission', RequirePermission::class);
        $router->aliasMiddleware('admin.super-admin', RequireSuperAdmin::class);
        Route::middleware(config('laravel-vben-admin.route.middleware', ['api']))
            ->prefix('api/admin')
            ->group(__DIR__.'/../routes/admin.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-vben-admin.php' => config_path('laravel-vben-admin.php'),
            ], 'laravel-vben-admin-config');

            $this->commands([
                InstallCommand::class,
                PublishAssetsCommand::class,
                PublishWorkspaceCommand::class,
                CreateAdminCommand::class,
                SyncSystemDataCommand::class,
            ]);
        }
    }
}
