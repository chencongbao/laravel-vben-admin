<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;

final class PublishAssetsCommandTest extends TestCase
{
    private string $source;

    protected function getPackageProviders($app): array
    {
        return [LaravelVbenAdminServiceProvider::class];
    }

    public function test_it_can_publish_assets_from_an_explicit_source_directory(): void
    {
        $this->source = storage_path('framework/testing/vben-admin-assets');
        File::deleteDirectory($this->source);
        File::ensureDirectoryExists($this->source.'/assets');
        File::put($this->source.'/index.html', '<html>custom build</html>');
        File::put($this->source.'/assets/app.js', 'console.log("custom build");');

        $this->artisan('vben-admin:publish-assets', [
            '--force' => true,
            '--source' => $this->source,
        ])
            ->expectsOutputToContain('Administration assets published')
            ->assertSuccessful();

        self::assertSame('<html>custom build</html>', File::get(public_path('admin/index.html')));
        self::assertFileExists(public_path('admin/assets/app.js'));
        self::assertSame([], glob(public_path('.admin-staging-*')) ?: []);
        self::assertSame([], glob(public_path('.admin-backup-*')) ?: []);
    }

    protected function tearDown(): void
    {
        if (isset($this->source)) {
            File::deleteDirectory($this->source);
        }

        File::deleteDirectory(public_path('admin'));

        parent::tearDown();
    }
}
