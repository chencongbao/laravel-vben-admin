<?php

namespace Chencongbao\LaravelVbenAdmin;

use Chencongbao\Foundation\Exceptions\TelegramTransportException;
use Chencongbao\Foundation\FoundationServiceProvider;
use Chencongbao\LaravelVbenAdmin\Console\BuildFrontendCommand;
use Chencongbao\LaravelVbenAdmin\Console\CreateAdminCommand;
use Chencongbao\LaravelVbenAdmin\Console\InstallCommand;
use Chencongbao\LaravelVbenAdmin\Console\PublishAssetsCommand;
use Chencongbao\LaravelVbenAdmin\Console\PublishProjectCommand;
use Chencongbao\LaravelVbenAdmin\Console\PublishWorkspaceCommand;
use Chencongbao\LaravelVbenAdmin\Console\SyncSystemDataCommand;
use Chencongbao\LaravelVbenAdmin\Console\UpdateCommand;
use Chencongbao\LaravelVbenAdmin\Contracts\AdminAlertReporter;
use Chencongbao\LaravelVbenAdmin\Contracts\AdminNotificationPublisher;
use Chencongbao\LaravelVbenAdmin\Contracts\AdminNotificationSource;
use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Contracts\Authorizer;
use Chencongbao\LaravelVbenAdmin\Contracts\LoginRecorder;
use Chencongbao\LaravelVbenAdmin\Contracts\ModuleRegistry;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\EnsureAdminUser;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\ForceHttps;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\RequirePermission;
use Chencongbao\LaravelVbenAdmin\Http\Middleware\RequireSuperAdmin;
use Chencongbao\LaravelVbenAdmin\Services\ActivityAuditRecorder;
use Chencongbao\LaravelVbenAdmin\Services\ActivityLoginRecorder;
use Chencongbao\LaravelVbenAdmin\Services\AuditLogRegistry;
use Chencongbao\LaravelVbenAdmin\Services\DatabaseAdminNotificationPublisher;
use Chencongbao\LaravelVbenAdmin\Services\DatabaseAdminNotificationSource;
use Chencongbao\LaravelVbenAdmin\Services\DatabaseAuthorizer;
use Chencongbao\LaravelVbenAdmin\Services\FoundationAdminAlertReporter;
use Chencongbao\LaravelVbenAdmin\Services\InMemoryModuleRegistry;
use Chencongbao\LaravelVbenAdmin\Services\TelegramMessageDispatcher;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Throwable;

final class LaravelVbenAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(FoundationServiceProvider::class);
        $defaultAdminConfig = require __DIR__.'/../config/laravel-vben-admin.php';
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-vben-admin.php', 'laravel-vben-admin');
        $configuredRoute = $this->app->make('config')->get('laravel-vben-admin.route', []);
        $this->app->make('config')->set(
            'laravel-vben-admin.route',
            array_replace($defaultAdminConfig['route'], is_array($configuredRoute) ? $configuredRoute : []),
        );
        $defaultLogConfig = require __DIR__.'/../config/vben-admin-log.php';
        $configuredLogConfig = $this->app->make('config')->get('vben-admin-log', []);
        $this->app->make('config')->set(
            'vben-admin-log',
            array_replace_recursive($defaultLogConfig, is_array($configuredLogConfig) ? $configuredLogConfig : []),
        );

        $this->app->singleton(ModuleRegistry::class, InMemoryModuleRegistry::class);
        $this->app->singleton(Authorizer::class, DatabaseAuthorizer::class);
        $this->app->singleton(AuditLogRegistry::class);
        $this->app->singleton(AdminAlertReporter::class, FoundationAdminAlertReporter::class);
        $this->app->singleton(AdminNotificationSource::class, DatabaseAdminNotificationSource::class);
        $this->app->singleton(AdminNotificationPublisher::class, DatabaseAdminNotificationPublisher::class);
        $this->app->singleton(TelegramMessageDispatcher::class);
        $this->app->scoped(AuditRecorder::class, ActivityAuditRecorder::class);
        $this->app->scoped(LoginRecorder::class, ActivityLoginRecorder::class);

        $this->app->booted(function (): void {
            config()->set(
                'activitylog.clean_after_days',
                config('laravel-vben-admin.activity_log.clean_after_days', 365),
            );

            $handler = $this->app->make(ExceptionHandler::class);
            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (Throwable $exception): void {
                    if ($exception instanceof TelegramTransportException) {
                        return;
                    }

                    $this->app->make(AdminAlertReporter::class)->reportSystemException($exception);
                });
            }
        });
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerProjectModules();

        $router->aliasMiddleware('admin.user', EnsureAdminUser::class);
        $router->aliasMiddleware('admin.permission', RequirePermission::class);
        $router->aliasMiddleware('admin.super-admin', RequireSuperAdmin::class);
        $routeMiddleware = (array) config('laravel-vben-admin.route.middleware', ['api']);
        if ((bool) config('laravel-vben-admin.route.force_https', false)) {
            $routeMiddleware[] = ForceHttps::class;
        }

        Route::middleware($routeMiddleware)
            ->prefix('api/admin')
            ->group(__DIR__.'/../routes/admin.php');

        $projectRoutes = base_path('routes/admin.php');
        if (File::isFile($projectRoutes)) {
            Route::middleware($routeMiddleware)
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
