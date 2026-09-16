<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class PublishProjectCommand extends Command
{
    protected $signature = 'vben-admin:publish-project
                            {--force : Replace existing project extension files}';

    protected $description = 'Publish the host Laravel administration extension scaffold';

    public function handle(): int
    {
        $source = dirname(__DIR__, 2).'/stubs/project';
        if (! File::isDirectory($source)) {
            $this->components->error("Project scaffold was not found at [{$source}].");

            return self::FAILURE;
        }

        $published = 0;
        $skipped = 0;
        foreach (File::allFiles($source) as $file) {
            $destination = base_path($file->getRelativePathname());
            if (File::exists($destination) && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            File::ensureDirectoryExists(dirname($destination));
            File::copy($file->getPathname(), $destination);
            $published++;
        }

        $workspace = resource_path('admin/workspace/index.vue');
        if (! File::exists($workspace)) {
            File::ensureDirectoryExists(dirname($workspace));
            File::copy(dirname(__DIR__, 2).'/frontend/apps/web-antd/src/views/dashboard/workspace/index.vue', $workspace);
            $published++;
        } else {
            $skipped++;
        }

        $this->components->info("Project administration scaffold published: {$published} file(s); {$skipped} existing file(s) preserved.");

        return self::SUCCESS;
    }
}
