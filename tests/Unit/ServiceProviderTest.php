<?php

namespace Microblink\IdImageUpload\Tests\Unit;

use Microblink\IdImageUpload\Services\ImageUploadService;
use Microblink\IdImageUpload\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_service_is_registered(): void
    {
        $this->assertTrue($this->app->bound('microblink-uploader'));
    }

    public function test_service_class_is_registered(): void
    {
        $this->assertTrue($this->app->bound(ImageUploadService::class));
    }

    public function test_resolves_as_singleton(): void
    {
        $instance1 = $this->app->make('microblink-uploader');
        $instance2 = $this->app->make('microblink-uploader');

        $this->assertSame($instance1, $instance2);
    }

    public function test_config_is_loaded(): void
    {
        $this->assertNotNull(config('microblink'));
        $this->assertNotNull(config('microblink.api_key'));
        $this->assertNotNull(config('microblink.api_endpoint'));
    }

    public function test_routes_are_loaded(): void
    {
        $this->assertTrue(
            $this->app['router']->has('microblink.upload')
        );

        $this->assertTrue(
            $this->app['router']->has('microblink.upload.multi-side')
        );

        $this->assertTrue(
            $this->app['router']->has('microblink.upload.base64')
        );

        $this->assertTrue(
            $this->app['router']->has('microblink.upload.multi-side.base64')
        );
    }
}
