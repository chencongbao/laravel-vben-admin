<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

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

        if (glob(database_path('migrations/*_create_personal_access_tokens_table.php')) === []) {
            $this->call('vendor:publish', [
                '--tag' => 'sanctum-migrations',
            ]);
        } else {
            $this->components->info('Sanctum migration already exists; publication skipped.');
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
        $this->line('After building the Vue application: php artisan vben-admin:publish-assets');

        return self::SUCCESS;
    }

    private function createDefaultAdministrators(): void
    {
        $this->createDefaultAccount('cmsadmin', 'Super Administrator', 'administrator');
        $this->createDefaultAccount('admin', 'Administrator', 'manager');
        $this->components->warn('Change the default password immediately after the first login.');
    }

    private function createDefaultAccount(string $username, string $name, string $roleCode): void
    {
        if (AdminUser::query()->where('username', $username)->exists()) {
            $this->components->warn("Administrator [{$username}] already exists; its password and roles were not changed.");

            return;
        }

        $role = AdminRole::query()->where('code', $roleCode)->firstOrFail();
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
