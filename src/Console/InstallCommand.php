<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'vben-admin:install {--force : Overwrite the published configuration}';

    protected $description = 'Publish Laravel Vben Admin configuration and show the safe installation steps';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'laravel-vben-admin-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->components->info('Configuration published.');
        $this->line('Next: php artisan migrate');
        $this->line('Then: php artisan vben-admin:sync --dry-run');
        $this->line('Then: php artisan vben-admin:sync');
        $this->line('Finally: php artisan vben-admin:create-admin');

        return self::SUCCESS;
    }
}
