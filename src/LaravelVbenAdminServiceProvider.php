<?php

namespace Chencongbao\LaravelVbenAdmin;

use Chencongbao\LaravelVbenAdmin\Console\BuildFrontendCommand;
use Chencongbao\LaravelVbenAdmin\Console\CreateAdminCommand;
use Chencongbao\LaravelVbenAdmin\Console\InstallCommand;
use Chencongbao\LaravelVbenAdmin\Console\PublishAssetsCommand;
use Chencongbao\LaravelVbenAdmin\Console\PublishProjectCommand;
use Chencongbao\LaravelVbenAdmin\Console\PublishWorkspaceCommand;
use Chencongbao\LaravelVbenAdmin\Console\SyncSystemDataCommand;
use Chencongbao\LaravelVbenAdmin\Console\UpdateCommand;
use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Contracts\Authorizer;
use Chencongbao\LaravelVbenAdmin\Contracts\LoginRecorder;
use Chencongbao\LaravelVbenAdmin\Contracts\ModuleRegistry;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\EnsureAdminUser;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\RequirePermission;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\RequireSuperAdmin;
use Chencongbao\LaravelVbenAdmin\Services\ActivityAuditRecorder;
use Chencongbao\LaravelVbenAdmin\Services\ActivityLoginRecorder;
use Chencongbao\LaravelVbenAdmin\Services\AuditLogRegistry;
use Chencongbao\LaravelVbenAdmin\Services\DatabaseAuthorizer;
use Chencongbao\LaravelVbenAdmin\Services\InMemoryModuleRegistry;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class LaravelVbenAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-vben-admin.php', 'laravel-vben-admin');
        $defaultLogConfig = require __DIR__.'/../config/vben-admin-log.php';
        $configuredLogConfig = $this->app->make('config')->get('vben-admin-log', []);
        $this->app->make('config')->set(
            'vben-admin-log',
            array_replace_recursive($defaultLogConfig, is_array($configuredLogConfig) ? $configuredLogConfig : []),
        );

        $this->app->singleton(ModuleRegistry::class, InMemoryModuleRegistry::class);
        $this->app->singleton(Authorizer::class, DatabaseAuthorizer::class);
        $this->app->singleton(AuditLogRegistry::class);
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
        $this->registerProjectModules();

        $router->aliasMiddleware('admin.user', EnsureAdminUser::class);
        $router->aliasMiddleware('admin.permission', RequirePermission::class);
        $router->aliasMiddleware('admin.super-admin', RequireSuperAdmin::class);
        Route::middleware(config('laravel-vben-admin.route.middleware', ['api']))
            ->prefix('api/admin')
            ->group(__DIR__.'/../routes/admin.php');

        $projectRoutes = base_path('routes/admin.php');
        if (File::isFile($projectRoutes)) {
            Route::middleware(config('laravel-vben-admin.route.middleware', ['api']))
                ->prefix('api/admin')
                ->group($projectRoutes);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-vben-admin.php' => config_path('laravel-vben-admin.php'),
                __DIR__.'/../config/vben-admin-log.php' => config_path('vben-admin-log.php'),
            ], 'laravel-vben-admin-config');

            $this->commands([
                InstallCommand::class,
                BuildFrontendCommand::class,
                PublishAssetsCommand::class,
                PublishProjectCommand::class,
                PublishWorkspaceCommand::class,
                CreateAdminCommand::class,
                SyncSystemDataCommand::class,
                UpdateCommand::class,
            ]);
        }
    }

    private function registerProjectModules(): void
    {
        $modulesFile = base_path('app/Admin/modules.php');
        if (! File::isFile($modulesFile)) {
            return;
        }

        $modules = require $modulesFile;
        if (! is_array($modules)) {
            return;
        }

        $registry = $this->app->make(ModuleRegistry::class);
        foreach ($modules as $moduleClass) {
            if (is_string($moduleClass) && class_exists($moduleClass)) {
                $registry->register($this->app->make($moduleClass));
            }
        }
    }
}
