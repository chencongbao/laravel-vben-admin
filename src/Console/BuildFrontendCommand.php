<?php

namespace Chencongbao\LaravelVbenAdmin\Console;

use Chencongbao\LaravelVbenAdmin\Support\AdminPath;
use Chencongbao\LaravelVbenAdmin\Support\FrontendRuntimeRequirements;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class BuildFrontendCommand extends Command
{
    protected $signature = 'vben-admin:build
                            {--frontend= : Shared frontend workspace; defaults to the installed package frontend}
                            {--project-root= : Host extension directory; defaults to resources/admin}
                            {--install : Install dependencies with the frozen pnpm lockfile before building}
                            {--publish : Publish the compiled assets after a successful build}
                            {--force : Replace existing published assets; only applies with --publish}';

    protected $description = 'Build the shared Vben frontend together with the host Laravel project extensions';

    public function handle(): int
    {
        $frontend = $this->resolvePath((string) $this->option('frontend'), dirname(__DIR__, 2).'/frontend');
        $projectRoot = $this->resolvePath((string) $this->option('project-root'), resource_path('admin'));

        if (! File::isFile($frontend.'/package.json') || ! File::isFile($frontend.'/pnpm-lock.yaml')) {
            $this->components->error("A valid frontend workspace was not found at [{$frontend}].");

            return self::FAILURE;
        }

        if (! File::isDirectory($projectRoot)) {
            $this->components->error("The project extension directory was not found at [{$projectRoot}]. Run php artisan vben-admin:publish-project first.");

            return self::FAILURE;
        }

        $nodeVersion = $this->readVersion(['node', '--version'], $frontend);
        $pnpmVersion = $this->readVersion(['pnpm', '--version'], $frontend);

        if ($nodeVersion === null || $pnpmVersion === null) {
            $this->components->error('Node.js and pnpm are required to build the administration frontend.');

            return self::FAILURE;
        }

        if (! FrontendRuntimeRequirements::supportsNode($nodeVersion)) {
            $this->components->error(
                "Unsupported Node.js [{$nodeVersion}]. This frontend requires Node.js ".FrontendRuntimeRequirements::NODE.'. Switch Node.js and retry.'
            );

            return self::FAILURE;
        }

        if (! FrontendRuntimeRequirements::supportsPnpm($pnpmVersion)) {
            $this->components->error(
                "Unsupported pnpm [{$pnpmVersion}]. This frontend requires pnpm ".FrontendRuntimeRequirements::PNPM.'. Upgrade pnpm and retry.'
            );

            return self::FAILURE;
        }

        $this->line("Node.js {$nodeVersion}; pnpm {$pnpmVersion}");

        if ($this->option('install') && ! $this->runProcess(
            ['pnpm', 'install', '--frozen-lockfile', '--force'],
            $frontend,
            ['CI' => 'true'],
        )) {
            return self::FAILURE;
        }

        if (! File::isDirectory($frontend.'/node_modules')) {
            $this->components->error('Frontend dependencies are missing. Re-run with --install.');

            return self::FAILURE;
        }

        $this->syncProjectExtensions($projectRoot, $frontend.'/apps/web-antd/src/project-admin');

        $environment = [
            'TURBO_FORCE' => 'true',
            'VBEN_ADMIN_WORKSPACE' => $projectRoot.'/workspace/index.vue',
            'VITE_BASE' => '/'.AdminPath::value().'/',
        ];

        if (! $this->runProcess(['pnpm', 'build:antd'], $frontend, $environment)) {
            return self::FAILURE;
        }

        $dist = $frontend.'/apps/web-antd/dist';
        if (! File::isFile($dist.'/index.html')) {
            $this->components->error("The build completed without producing [{$dist}/index.html].");

            return self::FAILURE;
        }

        if ($this->option('publish')) {
            return $this->call('vben-admin:publish-assets', [
                '--force' => (bool) $this->option('force'),
                '--source' => $dist,
            ]);
        }

        $this->components->info("Administration frontend built at [{$dist}].");

        return self::SUCCESS;
    }

    /** @param list<string> $command */
    private function runProcess(array $command, string $workingDirectory, array $environment = []): bool
    {
        $this->components->info(implode(' ', $command));
        $process = new Process($command, $workingDirectory, $environment ?: null, null, null);
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            $this->components->error("Command failed with exit code {$process->getExitCode()}.");

            return false;
        }

        return true;
    }

    private function resolvePath(string $option, string $default): string
    {
        return rtrim(trim($option) !== '' ? trim($option) : $default, DIRECTORY_SEPARATOR);
    }

    /** @param list<string> $command */
    private function readVersion(array $command, string $workingDirectory): ?string
    {
        $process = new Process($command, $workingDirectory, null, null, 30);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $version = ltrim(trim($process->getOutput()), 'vV');

        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1
            ? $version
            : null;
    }

    private function syncProjectExtensions(string $projectRoot, string $buildRoot): void
    {
        File::deleteDirectory($buildRoot);
        File::ensureDirectoryExists($buildRoot);

        foreach (['api', 'components', 'locales', 'pages'] as $directory) {
            $source = $projectRoot.'/'.$directory;
            if (File::isDirectory($source)) {
                File::copyDirectory($source, $buildRoot.'/'.$directory);
            } else {
                File::ensureDirectoryExists($buildRoot.'/'.$directory);
            }
        }
    }
}
