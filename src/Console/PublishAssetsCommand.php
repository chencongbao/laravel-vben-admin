<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

use Chencongbao\LaravelVbenAdmin\Support\AdminPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

final class PublishAssetsCommand extends Command
{
    protected $signature = 'vben-admin:publish-assets {--force : Replace an existing administration asset directory}';

    protected $description = 'Publish the compiled Vben administration application to its configured public path';

    public function handle(): int
    {
        $source = dirname(__DIR__, 2).'/frontend/apps/web-antd/dist';

        try {
            $path = AdminPath::value();
            $destination = AdminPath::publicDirectory();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! File::isDirectory($source) || ! File::exists($source.'/index.html')) {
            $this->components->error('Compiled frontend assets were not found. Build the package frontend first with: pnpm build:antd');

            return self::FAILURE;
        }

        if (File::isDirectory($destination) && count(File::allFiles($destination)) > 0) {
            if (! $this->option('force')) {
                $this->components->error("Destination [public/{$path}] is not empty. Use --force to replace it.");

                return self::FAILURE;
            }

            File::deleteDirectory($destination);
        }

        File::ensureDirectoryExists($destination);
        File::copyDirectory($source, $destination);
        $this->components->info("Administration assets published to [public/{$path}].");

        return self::SUCCESS;
    }
}
