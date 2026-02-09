<?php

namespace Microblink\IdImageUpload\Tests\Unit;

use Microblink\IdImageUpload\Facades\MicroblinkUploader;
use Microblink\IdImageUpload\Services\ImageUploadService;
use Microblink\IdImageUpload\Tests\TestCase;

class FacadeTest extends TestCase
{
    public function test_facade_resolves_to_service(): void
    {
        $this->assertInstanceOf(
            ImageUploadService::class,
            MicroblinkUploader::getFacadeRoot()
        );
    }

    public function test_facade_can_access_get_config(): void
    {
        $config = MicroblinkUploader::getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('api_endpoint', $config);
    }

    public function test_facade_can_set_api_key(): void
    {
        MicroblinkUploader::setApiKey('facade-test-key');

        $config = MicroblinkUploader::getConfig();
        $this->assertEquals('facade-test-key', $config['api_key']);
    }

    public function test_facade_can_set_endpoint(): void
    {
        MicroblinkUploader::setEndpoint('https://test.endpoint.com');

        $config = MicroblinkUploader::getConfig();
        $this->assertEquals('https://test.endpoint.com', $config['api_endpoint']);
    }
}
