<?php

namespace Microblink\IdImageUpload\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use Microblink\IdImageUpload\Exceptions\ApiException;
use Microblink\IdImageUpload\Exceptions\InvalidImageException;
use Microblink\IdImageUpload\Services\ImageUploadService;
use Microblink\IdImageUpload\Tests\TestCase;
use ReflectionMethod;

class ImageUploadServiceTest extends TestCase
{
    protected ImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new ImageUploadService([
            'api_key' => 'test-api-key',
            'api_endpoint' => 'https://api.microblink.com/v1/recognizers/blinkid',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry' => [
                'enabled' => false,
                'times' => 3,
                'sleep' => 1000,
            ],
            'validation' => [
                'allowed_mimes' => ['jpeg', 'jpg', 'png'],
                'max_size' => 10240,
                'min_width' => 640,
                'min_height' => 480,
            ],
            'headers' => [],
            'logging' => [
                'enabled' => false,
            ],
        ]);
    }

    public function test_can_get_config(): void
    {
        $config = $this->service->getConfig();

        $this->assertIsArray($config);
        $this->assertEquals('test-api-key', $config['api_key']);
        $this->assertEquals('https://api.microblink.com/v1/recognizers/blinkid', $config['api_endpoint']);
    }

    public function test_can_set_api_key(): void
    {
        $this->service->setApiKey('new-api-key');

        $config = $this->service->getConfig();
        $this->assertEquals('new-api-key', $config['api_key']);
    }

    public function test_can_set_endpoint(): void
    {
        $this->service->setEndpoint('https://api.microblink.com/v2/recognizers/new');

        $config = $this->service->getConfig();
        $this->assertEquals('https://api.microblink.com/v2/recognizers/new', $config['api_endpoint']);
    }

    public function test_can_update_config(): void
    {
        $this->service->setConfig([
            'timeout' => 60,
        ]);

        $config = $this->service->getConfig();
        $this->assertEquals(60, $config['timeout']);
    }

    public function test_validates_image_file_exists(): void
    {
        $this->expectException(InvalidImageException::class);
        $this->expectExceptionMessage('Image file not found');

        $this->service->upload('/non/existent/path.jpg');
    }

    public function test_validates_image_type(): void
    {
        // Create a text file with .txt extension (not in allowed_mimes)
        $tempPath = sys_get_temp_dir() . '/test_file_' . uniqid() . '.txt';
        file_put_contents($tempPath, 'This is not an image');

        $this->expectException(InvalidImageException::class);
        $this->expectExceptionMessage('Invalid file type');

        try {
            $this->service->upload($tempPath);
        } finally {
            unlink($tempPath);
        }
    }

    public function test_validates_image_size(): void
    {
        // Create a service with very small max size
        $service = new ImageUploadService([
            'api_key' => 'test',
            'api_endpoint' => 'https://api.microblink.com/v1/recognizers/blinkid',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry' => ['enabled' => false],
            'validation' => [
                'allowed_mimes' => ['jpeg', 'jpg', 'png'],
                'max_size' => 1, // 1KB max
                'min_width' => 10,
                'min_height' => 10,
            ],
            'headers' => [],
            'logging' => ['enabled' => false],
        ]);

        // Create a test image larger than 1KB
        $imagePath = $this->createTestImage(100, 100);

        $this->expectException(InvalidImageException::class);
        $this->expectExceptionMessage('exceeds the maximum allowed size');

        try {
            $service->upload($imagePath);
        } finally {
            $this->cleanUpTestImage($imagePath);
        }
    }

    public function test_validates_image_dimensions(): void
    {
        // Create a small image
        $imagePath = $this->createTestImage(100, 100);

        $this->expectException(InvalidImageException::class);
        $this->expectExceptionMessage('below the minimum required');

        try {
            $this->service->upload($imagePath);
        } finally {
            $this->cleanUpTestImage($imagePath);
        }
    }

    public function test_returns_chained_instance_from_setters(): void
    {
        $result = $this->service
            ->setApiKey('key')
            ->setEndpoint('endpoint')
            ->setConfig(['timeout' => 45]);

        $this->assertInstanceOf(ImageUploadService::class, $result);
    }

    public function test_build_headers_uses_api_key_only_when_no_secret(): void
    {
        $method = new ReflectionMethod(ImageUploadService::class, 'buildHeaders');
        $method->setAccessible(true);
        $headers = $method->invoke($this->service);

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertSame('Bearer test-api-key', $headers['Authorization']);
    }

    public function test_build_headers_uses_base64_token_when_secret_set(): void
    {
        $service = new ImageUploadService([
            'api_key' => 'key',
            'api_secret' => 'secret',
            'api_endpoint' => 'https://api.microblink.com/v1/recognizers/blinkid',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry' => ['enabled' => false],
            'validation' => [
                'allowed_mimes' => ['jpeg', 'jpg', 'png'],
                'max_size' => 10240,
                'min_width' => 640,
                'min_height' => 480,
            ],
            'headers' => [],
            'logging' => ['enabled' => false],
        ]);

        $method = new ReflectionMethod(ImageUploadService::class, 'buildHeaders');
        $method->setAccessible(true);
        $headers = $method->invoke($service);

        $expectedToken = base64_encode('key:secret');
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertSame('Bearer ' . $expectedToken, $headers['Authorization']);
    }

    public function test_handle_response_403_extracts_detail_message(): void
    {
        $response = new Response(403, [], '{"detail": "Invalid token"}');
        $method = new ReflectionMethod(ImageUploadService::class, 'handleResponse');
        $method->setAccessible(true);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid token');

        $method->invoke($this->service, $response);
    }

    public function test_handle_response_403_fallback_when_body_empty(): void
    {
        $response = new Response(403, [], '');
        $method = new ReflectionMethod(ImageUploadService::class, 'handleResponse');
        $method->setAccessible(true);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Empty or non-JSON response body');

        $method->invoke($this->service, $response);
    }
}
