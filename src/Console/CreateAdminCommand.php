<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

use Chencongbao\LaravelVbenAdmin\Models\AdminRole;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

final class CreateAdminCommand extends Command
{
    protected $signature = 'vben-admin:create-admin {username?} {--name=}';

    protected $description = 'Create a Laravel Vben Admin super administrator';

    public function handle(): int
    {
        $username = $this->argument('username') ?: $this->ask('Username');
        $name = $this->option('name') ?: $this->ask('Display name', $username);
        $password = $this->secret('Password');

        Validator::make(compact('username', 'name', 'password'), [
            'username' => ['required', 'string', 'max:120', 'unique:'.config('laravel-vben-admin.tables.users', 'admin_users').',username'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', Password::min(12)->letters()->mixedCase()->numbers()],
        ])->validate();

        $role = AdminRole::query()->where('code', 'super-admin')->where('is_super_admin', true)->firstOrFail();
        $user = AdminUser::query()->create(['username' => $username, 'name' => $name, 'password' => Hash::make($password), 'is_active' => true]);
        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        $this->components->info("Administrator [{$username}] created.");

        return self::SUCCESS;
    }
}
