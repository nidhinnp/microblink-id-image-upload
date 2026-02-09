<?php

namespace Microblink\IdImageUpload\Facades;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array upload(UploadedFile|string $image, array $options = [])
 * @method static array uploadForPassport(UploadedFile|string $image, array $options = [])
 * @method static array uploadForNationalId(UploadedFile|string $image, array $options = [])
 * @method static array uploadMultiSide(UploadedFile|string $frontImage, UploadedFile|string $backImage, array $options = [])
 * @method static array uploadNationalIdMultiSide(UploadedFile|string $frontImage, UploadedFile|string $backImage, array $options = [])
 * @method static array uploadBase64(string $base64Image, array $options = [])
 * @method static array uploadMultiSideBase64(string $frontBase64, string $backBase64, array $options = [])
 * @method static array getConfig()
 * @method static \Microblink\IdImageUpload\Services\ImageUploadService setConfig(array $config)
 * @method static \Microblink\IdImageUpload\Services\ImageUploadService setApiKey(string $apiKey)
 * @method static \Microblink\IdImageUpload\Services\ImageUploadService setEndpoint(string $endpoint)
 *
 * @see \Microblink\IdImageUpload\Services\ImageUploadService
 */
class MicroblinkUploader extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'microblink-uploader';
    }
}
