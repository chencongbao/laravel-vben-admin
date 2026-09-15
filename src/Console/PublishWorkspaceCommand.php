<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class PublishWorkspaceCommand extends Command
{
    protected $signature = 'vben-admin:publish-workspace {--force : Replace the existing project workspace source file}';

    protected $description = 'Publish an editable workspace Vue page into the Laravel project';

    public function handle(): int
    {
        $source = dirname(__DIR__, 2).'/frontend/apps/web-antd/src/views/dashboard/workspace/index.vue';
        $destination = resource_path('admin/workspace/index.vue');

        if (! File::isFile($source)) {
            $this->components->error("Default workspace source was not found at [{$source}].");

            return self::FAILURE;
        }

        if (File::exists($destination) && ! $this->option('force')) {
            $this->components->error('Project workspace already exists at [resources/admin/workspace/index.vue]. Use --force to replace it.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);

        $this->components->info('Editable workspace published to [resources/admin/workspace/index.vue].');
        $this->line('Build from the package frontend with:');
        $this->line('VBEN_ADMIN_WORKSPACE="'.$destination.'" VITE_BASE=/'.config('laravel-vben-admin.path', 'admin').'/ pnpm build:antd');
        $this->line('Then publish the compiled assets with: php artisan vben-admin:publish-assets --force');

        return self::SUCCESS;
    }
}
