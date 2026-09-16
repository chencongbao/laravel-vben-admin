<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

use Chencongbao\LaravelVbenAdmin\Contracts\ModuleRegistry;
use Illuminate\Console\Command;

final class UpdateCommand extends Command
{
    protected $signature = 'vben-admin:update
                            {--dry-run : Preview migration, system data and frontend actions without writing}
                            {--skip-migrate : Skip database migrations}
                            {--skip-frontend : Skip dependency installation, frontend build and asset publication}';

    protected $description = 'Safely update Laravel Vben Admin database data, frontend assets and caches';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            return $this->preview();
        }

        if ($this->call('vben-admin:publish-project') !== self::SUCCESS) {
            return self::FAILURE;
        }
        $this->registerPublishedModules();

        if (! $this->option('skip-migrate') && $this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->call('vben-admin:sync') !== self::SUCCESS) {
            return self::FAILURE;
        }

        if (! $this->option('skip-frontend') && $this->call('vben-admin:build', [
            '--install' => true,
            '--publish' => true,
            '--force' => true,
        ]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->call('optimize:clear') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->components->info('Laravel Vben Admin updated successfully.');
        if ($this->option('skip-frontend')) {
            $this->components->warn('Frontend build was skipped. Published assets were left unchanged.');
        }

        return self::SUCCESS;
    }

    private function preview(): int
    {
        $this->components->info('Laravel Vben Admin update preview; no files or database records will be written.');
        $this->line($this->option('skip-migrate')
            ? 'Database migrations: skipped by option.'
            : 'Database migrations: pending migrations will run during update.');

        if ($this->call('vben-admin:sync', ['--dry-run' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->line($this->option('skip-frontend')
            ? 'Frontend: skipped by option.'
            : 'Frontend: install locked dependencies, build project extensions and atomically publish assets.');
        $this->line('Project scaffold: existing files will be preserved; missing files will be added during update.');
        $this->line('Caches: Laravel optimized caches will be cleared after a successful update.');

        return self::SUCCESS;
    }

    private function registerPublishedModules(): void
    {
        $modulesFile = base_path('app/Admin/modules.php');
        if (! is_file($modulesFile)) {
            return;
        }

        $registry = $this->laravel->make(ModuleRegistry::class);
        $registeredKeys = collect($registry->all())->map->key()->all();
        foreach ((array) require $modulesFile as $moduleClass) {
            if (! is_string($moduleClass) || ! class_exists($moduleClass)) {
                continue;
            }

            $module = $this->laravel->make($moduleClass);
            if (! in_array($module->key(), $registeredKeys, true)) {
                $registry->register($module);
                $registeredKeys[] = $module->key();
            }
        }
    }
}
