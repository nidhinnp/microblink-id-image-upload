# Microblink ID Image Upload

A Laravel package for uploading ID/document images to the Microblink API. This is a backend-only package that supports image upload and submission to Microblink APIs for document verification and data extraction.

## Features

- Upload ID/document images to Microblink API
- **Supports both passport and national ID** (and other ID document types) — one upload flow; the Microblink API detects the document type and returns parsed data (e.g. `documentType`, `firstName`, `lastName`, `documentNumber`, MRZ, etc.)
- Single-sided documents (e.g. passport photo page) via `upload()` / `uploadBase64()`
- Two-sided documents (e.g. national ID card front/back) via `uploadMultiSide()` / `uploadMultiSideBase64()`
- Accept file uploads or base64 encoded images
- Automatic image validation (type, size, dimensions)
- Configurable retry mechanism with exponential backoff
- Laravel Facade for easy access
- Optional API routes for quick integration
- Compatible with Laravel 9, 10, and 11

## Requirements

- PHP 8.0 or higher
- Laravel 9.x, 10.x, or 11.x
- Guzzle HTTP 7.0 or higher

## Installation

### Via Composer

```bash
composer require nidhinnp/microblink-id-image-upload
```

### Local Development

For local development, add the package to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/microblink-id-image-upload"
        }
    ],
    "require": {
        "nidhinnp/microblink-id-image-upload": "*"
    }
}
```

Then run:

```bash
composer update
```

## Configuration

### Publish the Configuration

```bash
php artisan vendor:publish --tag=microblink-config
```

This will create `config/microblink.php` in your application.

### Environment Variables

Add these to your `.env` file:

```env
MICROBLINK_API_KEY=your-api-key-here
MICROBLINK_API_ENDPOINT=https://api.microblink.com/v1/recognizers/blinkid
```

For **Microblink Cloud API**, you must set **both** `MICROBLINK_API_KEY` and `MICROBLINK_API_SECRET`. The package builds the Bearer token as `Base64(api_key:api_secret)` per [Microblink's documentation](https://docs.microblink.com/documentation/cloudapi/overview.html). Create these in the Microblink dashboard as **Cloud API** credentials (not the same as the BlinkID Web SDK license key). If you use a pre-built token instead, put it in `MICROBLINK_API_KEY` and leave `MICROBLINK_API_SECRET` unset.

### Optional Environment Variables

```env
MICROBLINK_API_SECRET=your-api-secret-here
MICROBLINK_TIMEOUT=30
MICROBLINK_CONNECT_TIMEOUT=10
MICROBLINK_RETRY_ENABLED=true
MICROBLINK_RETRY_TIMES=3
MICROBLINK_RETRY_SLEEP=1000
MICROBLINK_LOGGING_ENABLED=false
MICROBLINK_LOG_CHANNEL=stack
```

### Passport vs National ID — different URLs

Microblink exposes **different recognizer endpoints** per use case:

| Use case | Endpoint | When to use |
|----------|----------|-------------|
| **Passport** (MRZ) | `/v1/recognizers/passport` | Passport photo page only |
| **National ID / any doc** (front) | `/v1/recognizers/blinkid` | National ID, passport, driver's license, etc. |
| **National ID** (front + back) | `/v1/recognizers/blinkid-multi-side` | Two-sided ID cards |

You can set them in `.env`:

```env
MICROBLINK_API_ENDPOINT=https://api.microblink.com/v1/recognizers/blinkid
MICROBLINK_PASSPORT_ENDPOINT=https://api.microblink.com/v1/recognizers/passport
MICROBLINK_BLINKID_ENDPOINT=https://api.microblink.com/v1/recognizers/blinkid
MICROBLINK_BLINKID_MULTI_SIDE_ENDPOINT=https://api.microblink.com/v1/recognizers/blinkid-multi-side
```

Use the **passport** endpoint when you only accept passports (MRZ scanning). Use **blinkid** when you accept national IDs or multiple document types. The package provides helpers that pick the correct URL from config:

```php
// Uses config endpoints.passport (or MICROBLINK_PASSPORT_ENDPOINT)
    $response = MicroblinkUploader::uploadForPassport($request->file('image'));

// Uses config endpoints.blinkid (or MICROBLINK_BLINKID_ENDPOINT)
$response = MicroblinkUploader::uploadForNationalId($request->file('image'));

// Front + back for national ID (uses endpoints.blinkid_multi_side)
$response = MicroblinkUploader::uploadNationalIdMultiSide(
    $request->file('front_image'),
    $request->file('back_image')
);
```

If you don’t set the optional `endpoints.*` / env vars, these methods fall back to `api_endpoint` (and the default multi-side URL).

## Usage

### Using the Facade

```php
use MicroblinkUploader;

// Upload a single image (uses default api_endpoint)
$response = MicroblinkUploader::upload($request->file('image'));

// Passport: uses passport recognizer URL
$response = MicroblinkUploader::uploadForPassport($request->file('passport_image'));

// National ID (front only): uses BlinkID recognizer URL
$response = MicroblinkUploader::uploadForNationalId($request->file('id_image'));

// Upload from file path
$response = MicroblinkUploader::upload('/path/to/image.jpg');

// Upload front and back of document
$response = MicroblinkUploader::uploadMultiSide(
    $request->file('front_image'),
    $request->file('back_image')
);

// Upload base64 encoded image
$response = MicroblinkUploader::uploadBase64($base64ImageString);

// Upload front and back as base64
$response = MicroblinkUploader::uploadMultiSideBase64(
    $frontBase64,
    $backBase64
);
```

### Using Dependency Injection

```php
use Microblink\IdImageUpload\Services\ImageUploadService;

class MyController extends Controller
{
    public function __construct(
        protected ImageUploadService $microblinkService
    ) {}

    public function verify(Request $request)
    {
        $response = $this->microblinkService->upload(
            $request->file('document')
        );

        return response()->json($response);
    }
}
```

### Runtime Configuration

```php
use MicroblinkUploader;

// Set API key at runtime
MicroblinkUploader::setApiKey('new-api-key');

// Set endpoint at runtime
MicroblinkUploader::setEndpoint('https://api.microblink.com/v1/recognizers/blinkid-multi-side');

// Override multiple config options
MicroblinkUploader::setConfig([
    'timeout' => 60,
    'retry' => [
        'enabled' => true,
        'times' => 5,
    ],
]);
```

### With Custom Options

```php
$response = MicroblinkUploader::upload(
    $request->file('image'),
    [
        'endpoint' => 'https://api.microblink.com/v1/recognizers/custom',
    ]
);
```

## API Routes

The package registers the following routes automatically:

| Method | URI | Description |
|--------|-----|-------------|
| POST | `/api/microblink/image-upload` | Upload single image |
| POST | `/api/microblink/image-upload/multi-side` | Upload front & back images |
| POST | `/api/microblink/image-upload/base64` | Upload base64 image |
| POST | `/api/microblink/image-upload/multi-side/base64` | Upload front & back base64 |

### Example API Requests

**Single Image Upload:**

```bash
curl -X POST http://your-app.test/api/microblink/image-upload \
  -H "Accept: application/json" \
  -F "image=@/path/to/id-card.jpg"
```

**Multi-Side Upload:**

```bash
curl -X POST http://your-app.test/api/microblink/image-upload/multi-side \
  -H "Accept: application/json" \
  -F "front_image=@/path/to/front.jpg" \
  -F "back_image=@/path/to/back.jpg"
```

**Base64 Upload:**

```bash
curl -X POST http://your-app.test/api/microblink/image-upload/base64 \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"image": "base64_encoded_image_data"}'
```

### Publishing Routes

To customize the routes, publish them:

```bash
php artisan vendor:publish --tag=microblink-routes
```

This will copy `routes/microblink.php` to your application. Then, load it in your `RouteServiceProvider`:

```php
Route::middleware('api')
    ->group(base_path('routes/microblink.php'));
```

## Supported document types

The package does not distinguish between document types in your code. You use the same upload methods for:

- **Passport** — single image (photo page); use `upload()` or `uploadBase64()`.
- **National ID / ID card** — often two-sided; use `uploadMultiSide()` (or `upload()` for front-only).
- **Driver’s license** and other supported ID documents — same flow.

The Microblink API detects the document type from the image and returns a parsed result (e.g. `documentType`, `firstName`, `lastName`, `documentNumber`, MRZ, dates). Inspect the `data` (or `result`) in the response to see the detected type and extracted fields.

## Response format

### Successful response

```json
{
    "success": true,
    "data": {
        "result": {
            "documentType": "PASSPORT",
            "firstName": "JOHN",
            "lastName": "DOE",
            "dateOfBirth": {
                "day": 15,
                "month": 6,
                "year": 1990
            },
            "documentNumber": "AB123456",
            "nationality": "USA",
            "sex": "M"
        },
        "processingStatus": "SUCCESS"
    }
}
```

### Error Response

```json
{
    "success": false,
    "error": "api_error",
    "message": "Unauthorized: Invalid API key"
}
```

## Error Handling

The package throws specific exceptions for different error types:

```php
use Microblink\IdImageUpload\Exceptions\InvalidImageException;
use Microblink\IdImageUpload\Exceptions\ApiException;
use Microblink\IdImageUpload\Exceptions\ImageUploadException;

try {
    $response = MicroblinkUploader::upload($image);
} catch (InvalidImageException $e) {
    // Image validation failed (wrong format, size, dimensions)
    Log::warning('Invalid image: ' . $e->getMessage());
} catch (ApiException $e) {
    // Microblink API returned an error (message includes API detail when available)
    Log::error('API Error: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')');
} catch (ImageUploadException $e) {
    // General upload failure
    Log::error('Upload failed: ' . $e->getMessage());
}
```

## Troubleshooting

### How to tell what went wrong

The package throws `ApiException` with clear messages and HTTP code so you can tell key vs URL issues:

| HTTP code | Meaning | What to check |
|-----------|---------|----------------|
| **401** | Invalid or missing credentials | Set `MICROBLINK_API_KEY` and `MICROBLINK_API_SECRET`; use Cloud API credentials, not a Web SDK license key. |
| **403** | Access denied (wrong credentials type) | Same as 401: use Cloud API key + secret from Microblink dashboard, not the BlinkID license key. |
| **404** | Wrong URL or endpoint | Check `MICROBLINK_API_ENDPOINT` (e.g. `https://api.microblink.com/v1/recognizers/blinkid` or `.../passport`). |
| **400** | Bad request (e.g. invalid image) | Check image format, size, and that the request body matches what the API expects. |
| **429** | Rate limit exceeded | Reduce request frequency or upgrade your plan. |
| **5xx** | Microblink server error | Retry later; check [Microblink status](https://microblink.com) if it persists. |

The exception message includes the API response when available, plus a short hint for each code.

### 403 Forbidden

- **Cloud API requires two credentials**: Set both `MICROBLINK_API_KEY` and `MICROBLINK_API_SECRET`. The package sends `Bearer Base64(api_key:api_secret)`. Using only the key (or using a **BlinkID Web SDK license key** here) will result in 403. The [web-sdks](https://github.com/microblink/web-sdks) repo uses a **license key** for in-browser scanning; this package uses **Cloud API** credentials (key + secret) for server-side uploads—they are different.
- **Pre-built token**: If your provider gives you a single token, put it in `MICROBLINK_API_KEY` and leave `MICROBLINK_API_SECRET` unset.
- Check that your API key has access to the recognizer (e.g. passport) in the [Microblink dashboard](https://microblink.com/login).

The exception message will include the API's error detail when the response body contains it (`message`, `error`, `detail`, or similar).

## Image Validation

The package validates images before upload:

- **Allowed formats:** jpeg, jpg, png, gif, bmp, webp
- **Maximum size:** 10MB (configurable)
- **Minimum dimensions:** 640x480 (configurable)

Customize validation in `config/microblink.php`:

```php
'validation' => [
    'allowed_mimes' => ['jpeg', 'jpg', 'png'],
    'max_size' => 5120, // 5MB in KB
    'min_width' => 800,
    'min_height' => 600,
],
```

## Logging

Enable logging for debugging:

```env
MICROBLINK_LOGGING_ENABLED=true
MICROBLINK_LOG_CHANNEL=stack
```

## Testing

```bash
composer test
```

Or run PHPUnit directly:

```bash
./vendor/bin/phpunit
```

## Security

- Never commit your API key to version control
- Use environment variables for sensitive configuration
- Consider adding authentication middleware to the API routes
- Validate and sanitize all user inputs

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Relationship to Microblink web-sdks

The [microblink/web-sdks](https://github.com/microblink/web-sdks) repo provides the **BlinkID Web SDK** (browser-based scanning with camera or photo upload, using a **license key**). This package is **backend-only** and uses the **Microblink Cloud API** (REST) with **api_key + api_secret**. They are different products and use different credentials: do not use a web SDK license key as `MICROBLINK_API_KEY`; create Cloud API credentials in the Microblink dashboard for this package.

## Credits

- Inspired by [Microblink BlinkID Web SDK](https://github.com/microblink/web-sdks)

## Support

For issues with the Microblink API itself, please contact [Microblink Support](https://microblink.com/contact).

For package-related issues, please open a GitHub issue.
