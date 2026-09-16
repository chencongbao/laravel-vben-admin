<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

use Chencongbao\LaravelVbenAdmin\Support\AdminPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

final class PublishAssetsCommand extends Command
{
    protected $signature = 'vben-admin:publish-assets
                            {--source= : Directory containing the compiled frontend index.html}
                            {--force : Replace an existing administration asset directory}';

    protected $description = 'Publish the compiled Vben administration application to its configured public path';

    public function handle(): int
    {
        $sourceOption = trim((string) $this->option('source'));
        $source = rtrim(
            $sourceOption !== '' ? $sourceOption : dirname(__DIR__, 2).'/frontend/apps/web-antd/dist',
            DIRECTORY_SEPARATOR,
        );

        try {
            $path = AdminPath::value();
            $destination = AdminPath::publicDirectory();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! File::isDirectory($source) || ! File::exists($source.'/index.html')) {
            $this->components->error(
                "Compiled frontend assets were not found at [{$source}]. "
                .'Build the package frontend first with: pnpm build:antd, '
                .'or pass --source=/absolute/path/to/dist.'
            );

            return self::FAILURE;
        }

        $hasExistingAssets = File::isDirectory($destination) && count(File::allFiles($destination)) > 0;
        if ($hasExistingAssets) {
            if (! $this->option('force')) {
                $this->components->error("Destination [public/{$path}] is not empty. Use --force to replace it.");

                return self::FAILURE;
            }
        }
        if (File::isDirectory($destination) && ! $hasExistingAssets) {
            File::deleteDirectory($destination);
        }

        $suffix = Str::lower(Str::random(12));
        $staging = dirname($destination).'/.'.basename($destination).'-staging-'.$suffix;
        $backup = dirname($destination).'/.'.basename($destination).'-backup-'.$suffix;

        File::deleteDirectory($staging);
        File::deleteDirectory($backup);
        File::ensureDirectoryExists($staging);
        if (! File::copyDirectory($source, $staging) || ! File::isFile($staging.'/index.html')) {
            File::deleteDirectory($staging);
            $this->components->error('Compiled assets could not be prepared for atomic publication. Existing assets were preserved.');

            return self::FAILURE;
        }

        try {
            if ($hasExistingAssets && ! File::moveDirectory($destination, $backup)) {
                throw new \RuntimeException('Existing administration assets could not be moved to a backup directory.');
            }
            if (! File::moveDirectory($staging, $destination)) {
                throw new \RuntimeException('Prepared administration assets could not be activated.');
            }
            File::deleteDirectory($backup);
        } catch (Throwable $exception) {
            File::deleteDirectory($staging);
            if (! File::isDirectory($destination) && File::isDirectory($backup)) {
                File::moveDirectory($backup, $destination);
            }
            $this->components->error($exception->getMessage().' Existing assets were preserved when recovery was possible.');

            return self::FAILURE;
        }

        $this->components->info("Administration assets published to [public/{$path}].");

        return self::SUCCESS;
    }
}
