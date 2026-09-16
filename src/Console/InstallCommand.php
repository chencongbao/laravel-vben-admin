<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

use Chencongbao\LaravelVbenAdmin\Contracts\ModuleRegistry;
use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

final class InstallCommand extends Command
{
    protected $signature = 'vben-admin:install {--force : Overwrite the published configuration}';

    protected $description = 'Install Laravel Vben Admin and create the default administrator';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'laravel-vben-admin-config',
            '--force' => (bool) $this->option('force'),
        ]);

        if ($this->call('vben-admin:publish-project') !== self::SUCCESS) {
            return self::FAILURE;
        }
        $this->registerPublishedModules();

        if (glob(database_path('migrations/*_create_personal_access_tokens_table.php')) === []) {
            $this->call('vendor:publish', [
                '--tag' => 'sanctum-migrations',
            ]);
        } else {
            $this->components->info('Sanctum migration already exists; publication skipped.');
        }

        foreach (glob(database_path('migrations/*_create_personal_access_tokens_table.php')) ?: [] as $migration) {
            if ($this->call('migrate', [
                '--path' => $migration,
                '--realpath' => true,
                '--force' => true,
            ]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->call('vben-admin:sync') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->createDefaultAdministrators();

        $this->components->info('Laravel Vben Admin installed successfully.');
        $this->line('Administration URL: /'.config('laravel-vben-admin.path', 'admin'));
        $this->line('Build and publish the Vue application with: php artisan vben-admin:build --install --publish --force');

        return self::SUCCESS;
    }

    private function createDefaultAdministrators(): void
    {
        $this->createDefaultAccount('cmsadmin', 'Super Administrator', 'administrator');
        $this->createDefaultAccount('admin', 'Administrator', 'manager');
        $this->components->warn('Change the default password immediately after the first login.');
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

    private function createDefaultAccount(string $username, string $name, string $roleCode): void
    {
        $role = AdminRole::query()->where('code', $roleCode)->firstOrFail();
        $existingUser = AdminUser::query()->where('username', $username)->first();
        if ($existingUser) {
            $existingUser->roles()->sync([$role->getKey()]);
            $this->components->warn("Administrator [{$username}] already exists; its password was not changed and its fixed role [{$roleCode}] was restored.");

            return;
        }

        $user = AdminUser::query()->create([
            'username' => $username,
            'name' => $name,
            'password' => Hash::make('admin'),
            'is_active' => true,
        ]);
        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        $this->components->info("Default administrator created: {$username} / admin [{$roleCode}]");
    }
}
