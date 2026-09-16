<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;

final class PublishProjectCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelVbenAdminServiceProvider::class];
    }

    public function test_it_publishes_the_project_extension_without_overwriting_existing_files(): void
    {
        $this->deletePublishedFiles();

        $this->artisan('vben-admin:publish-project')
            ->expectsOutputToContain('Project administration scaffold published')
            ->assertSuccessful();

        self::assertFileExists(base_path('routes/admin.php'));
        self::assertFileExists(base_path('app/Admin/modules.php'));
        self::assertFileExists(resource_path('admin/pages/demo/index.vue'));
        self::assertFileExists(resource_path('admin/locales/zh-CN/demo.json'));
        self::assertFileExists(resource_path('admin/workspace/index.vue'));

        File::put(base_path('routes/admin.php'), '<?php // project route');
        $this->artisan('vben-admin:publish-project')->assertSuccessful();
        self::assertSame('<?php // project route', File::get(base_path('routes/admin.php')));
    }

    protected function tearDown(): void
    {
        $this->deletePublishedFiles();

        parent::tearDown();
    }

    private function deletePublishedFiles(): void
    {
        File::delete(base_path('routes/admin.php'));
        File::deleteDirectory(base_path('app/Admin'));
        File::deleteDirectory(resource_path('admin'));
    }
}
