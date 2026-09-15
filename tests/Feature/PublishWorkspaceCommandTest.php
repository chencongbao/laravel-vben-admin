<?php

namespace Chencongbao\LaravelVbenAdmin\Tests\Feature;

use Chencongbao\LaravelVbenAdmin\LaravelVbenAdminServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;

final class PublishWorkspaceCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelVbenAdminServiceProvider::class];
    }

    public function test_it_publishes_an_editable_workspace_without_overwriting_it_by_default(): void
    {
        $destination = resource_path('admin/workspace/index.vue');
        File::delete($destination);

        $this->artisan('vben-admin:publish-workspace')
            ->expectsOutputToContain('Editable workspace published')
            ->assertSuccessful();

        self::assertFileExists($destination);
        self::assertStringContainsString('<WorkbenchHeader', File::get($destination));

        File::put($destination, '<template>project workspace</template>');

        $this->artisan('vben-admin:publish-workspace')->assertFailed();
        self::assertSame('<template>project workspace</template>', File::get($destination));

        $this->artisan('vben-admin:publish-workspace', ['--force' => true])->assertSuccessful();
        self::assertStringContainsString('<WorkbenchHeader', File::get($destination));
    }

    protected function tearDown(): void
    {
        File::delete(resource_path('admin/workspace/index.vue'));

        parent::tearDown();
    }
}
