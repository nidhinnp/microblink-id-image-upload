<?php

namespace Microblink\IdImageUpload\Tests;

use Microblink\IdImageUpload\MicroblinkImageUploadServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            MicroblinkImageUploadServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'MicroblinkUploader' => \Microblink\IdImageUpload\Facades\MicroblinkUploader::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('microblink.api_key', 'test-api-key');
        $app['config']->set('microblink.api_endpoint', 'https://api.microblink.com/v1/recognizers/blinkid');
        $app['config']->set('microblink.timeout', 30);
        $app['config']->set('microblink.connect_timeout', 10);
        $app['config']->set('microblink.retry.enabled', false);
        $app['config']->set('microblink.logging.enabled', false);
    }

    /**
     * Create a mock uploaded file for testing.
     */
    protected function createTestImage(int $width = 800, int $height = 600): string
    {
        $tempPath = sys_get_temp_dir() . '/test_image_' . uniqid() . '.jpg';
        
        $image = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgColor);
        imagejpeg($image, $tempPath, 90);
        imagedestroy($image);

        return $tempPath;
    }

    /**
     * Clean up test images.
     */
    protected function cleanUpTestImage(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
