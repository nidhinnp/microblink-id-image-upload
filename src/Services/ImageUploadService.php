<?php

namespace Microblink\IdImageUpload\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Microblink\IdImageUpload\Exceptions\ImageUploadException;
use Microblink\IdImageUpload\Exceptions\InvalidImageException;
use Microblink\IdImageUpload\Exceptions\ApiException;

class ImageUploadService
{
    /**
     * Configuration array.
     */
    protected array $config;

    /**
     * Guzzle HTTP client.
     */
    protected Client $client;

    /**
     * Create a new ImageUploadService instance.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeClient();
    }

    /**
     * Initialize the Guzzle HTTP client with retry middleware.
     */
    protected function initializeClient(): void
    {
        $stack = HandlerStack::create();

        // Add retry middleware if enabled
        if ($this->config['retry']['enabled'] ?? false) {
            $stack->push($this->createRetryMiddleware());
        }

        $this->client = new Client([
            'handler' => $stack,
            'timeout' => $this->config['timeout'] ?? 30,
            'connect_timeout' => $this->config['connect_timeout'] ?? 10,
            'http_errors' => false,
        ]);
    }

    /**
     * Create retry middleware for failed requests.
     */
    protected function createRetryMiddleware(): callable
    {
        return Middleware::retry(
            function (
                int $retries,
                Request $request,
                ?Response $response = null,
                ?\Exception $exception = null
            ): bool {
                $maxRetries = $this->config['retry']['times'] ?? 3;

                // Don't retry if we've already tried enough times
                if ($retries >= $maxRetries) {
                    return false;
                }

                // Retry on connection errors
                if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
                    $this->log('warning', 'Connection error, retrying...', [
                        'attempt' => $retries + 1,
                        'max_retries' => $maxRetries,
                    ]);
                    return true;
                }

                // Retry on server errors (5xx)
                if ($response && $response->getStatusCode() >= 500) {
                    $this->log('warning', 'Server error, retrying...', [
                        'attempt' => $retries + 1,
                        'status_code' => $response->getStatusCode(),
                    ]);
                    return true;
                }

                // Retry on rate limiting (429)
                if ($response && $response->getStatusCode() === 429) {
                    $this->log('warning', 'Rate limited, retrying...', [
                        'attempt' => $retries + 1,
                    ]);
                    return true;
                }

                return false;
            },
            function (int $retries): int {
                // Exponential backoff
                $sleep = $this->config['retry']['sleep'] ?? 1000;
                return $sleep * (2 ** $retries);
            }
        );
    }

    /**
     * Upload a single image to Microblink API.
     *
     * @param UploadedFile|string $image The image file or path
     * @param array $options Additional options for the API request
     * @return array The parsed JSON response from the API
     * @throws ImageUploadException
     * @throws InvalidImageException
     * @throws ApiException
     */
    public function upload(UploadedFile|string $image, array $options = []): array
    {
        $this->validateImage($image);

        $imageData = $this->prepareImageData($image);

        return $this->sendRequest($imageData, $options);
    }

    /**
     * Upload a passport image (MRZ). Uses the passport-specific recognizer endpoint.
     *
     * @param UploadedFile|string $image The passport image file or path
     * @param array $options Additional options for the API request
     * @return array The parsed JSON response from the API
     * @throws ImageUploadException
     * @throws InvalidImageException
     * @throws ApiException
     */
    public function uploadForPassport(UploadedFile|string $image, array $options = []): array
    {
        $endpoint = $this->config['endpoints']['passport'] ?? $this->config['api_endpoint'];
        $options['endpoint'] = $options['endpoint'] ?? $endpoint;
        return $this->upload($image, $options);
    }

    /**
     * Upload a national ID / ID card image (front). Uses the BlinkID recognizer endpoint.
     *
     * @param UploadedFile|string $image The front-side image file or path
     * @param array $options Additional options for the API request
     * @return array The parsed JSON response from the API
     * @throws ImageUploadException
     * @throws InvalidImageException
     * @throws ApiException
     */
    public function uploadForNationalId(UploadedFile|string $image, array $options = []): array
    {
        $endpoint = $this->config['endpoints']['blinkid'] ?? $this->config['api_endpoint'];
        $options['endpoint'] = $options['endpoint'] ?? $endpoint;
        return $this->upload($image, $options);
    }

    /**
     * Upload front and back images for two-sided documents.
     *
     * @param UploadedFile|string $frontImage The front side image
     * @param UploadedFile|string $backImage The back side image
     * @param array $options Additional options for the API request
     * @return array The parsed JSON response from the API
     * @throws ImageUploadException
     * @throws InvalidImageException
     * @throws ApiException
     */
    public function uploadMultiSide(
        UploadedFile|string $frontImage,
        UploadedFile|string $backImage,
        array $options = []
    ): array {
        $this->validateImage($frontImage);
        $this->validateImage($backImage);

        $frontData = $this->prepareImageData($frontImage);
        $backData = $this->prepareImageData($backImage);

        return $this->sendMultiSideRequest($frontData, $backData, $options);
    }

    /**
     * Upload front and back of national ID / ID card. Uses the BlinkID multi-side endpoint.
     *
     * @param UploadedFile|string $frontImage The front side image
     * @param UploadedFile|string $backImage The back side image
     * @param array $options Additional options for the API request
     * @return array The parsed JSON response from the API
     * @throws ImageUploadException
     * @throws InvalidImageException
     * @throws ApiException
     */
    public function uploadNationalIdMultiSide(
        UploadedFile|string $frontImage,
        UploadedFile|string $backImage,
        array $options = []
    ): array {
        $endpoint = $this->config['endpoints']['blinkid_multi_side'] ?? null;
        if ($endpoint !== null) {
            $options['endpoint'] = $options['endpoint'] ?? $endpoint;
        }
        return $this->uploadMultiSide($frontImage, $backImage, $options);
    }

    /**
     * Upload an image from base64 encoded string.
     *
     * @param string $base64Image Base64 encoded image data
     * @param array $options Additional options for the API request
     * @return array The parsed JSON response from the API
     * @throws ImageUploadException
     * @throws ApiException
     */
    public function uploadBase64(string $base64Image, array $options = []): array
    {
        // Remove data URI prefix if present
        if (preg_match('/^data:image\/\w+;base64,/', $base64Image)) {
            $base64Image = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);
        }

        return $this->sendJsonRequest([
            'imageSource' => $base64Image,
        ], $options);
    }

    /**
     * Upload front and back base64 images for two-sided documents.
     *
     * @param string $frontBase64 Base64 encoded front image
     * @param string $backBase64 Base64 encoded back image
     * @param array $options Additional options for the API request
     * @return array The parsed JSON response from the API
     * @throws ImageUploadException
     * @throws ApiException
     */
    public function uploadMultiSideBase64(
        string $frontBase64,
        string $backBase64,
        array $options = []
    ): array {
        // Remove data URI prefix if present
        if (preg_match('/^data:image\/\w+;base64,/', $frontBase64)) {
            $frontBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $frontBase64);
        }
        if (preg_match('/^data:image\/\w+;base64,/', $backBase64)) {
            $backBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $backBase64);
        }

        return $this->sendJsonRequest([
            'imageFrontSide' => $frontBase64,
            'imageBackSide' => $backBase64,
        ], $options);
    }

    /**
     * Validate the image file.
     *
     * @throws InvalidImageException
     */
    protected function validateImage(UploadedFile|string $image): void
    {
        if ($image instanceof UploadedFile) {
            $this->validateUploadedFile($image);
        } else {
            $this->validateImagePath($image);
        }
    }

    /**
     * Validate an uploaded file.
     *
     * @throws InvalidImageException
     */
    protected function validateUploadedFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new InvalidImageException('The uploaded file is not valid.');
        }

        $allowedMimes = $this->config['validation']['allowed_mimes'] ?? ['jpeg', 'jpg', 'png', 'gif', 'bmp', 'webp'];
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedMimes)) {
            throw new InvalidImageException(
                "Invalid file type '{$extension}'. Allowed types: " . implode(', ', $allowedMimes)
            );
        }

        $maxSize = ($this->config['validation']['max_size'] ?? 10240) * 1024; // Convert KB to bytes
        if ($file->getSize() > $maxSize) {
            throw new InvalidImageException(
                'File size exceeds the maximum allowed size of ' . 
                ($this->config['validation']['max_size'] ?? 10240) . 'KB.'
            );
        }

        // Validate image dimensions
        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            throw new InvalidImageException('Unable to read image dimensions.');
        }

        [$width, $height] = $imageInfo;
        $minWidth = $this->config['validation']['min_width'] ?? 640;
        $minHeight = $this->config['validation']['min_height'] ?? 480;

        if ($width < $minWidth || $height < $minHeight) {
            throw new InvalidImageException(
                "Image dimensions ({$width}x{$height}) are below the minimum required ({$minWidth}x{$minHeight})."
            );
        }
    }

    /**
     * Validate an image path.
     *
     * @throws InvalidImageException
     */
    protected function validateImagePath(string $path): void
    {
        if (!file_exists($path)) {
            throw new InvalidImageException("Image file not found at path: {$path}");
        }

        if (!is_readable($path)) {
            throw new InvalidImageException("Image file is not readable: {$path}");
        }

        $allowedMimes = $this->config['validation']['allowed_mimes'] ?? ['jpeg', 'jpg', 'png', 'gif', 'bmp', 'webp'];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedMimes)) {
            throw new InvalidImageException(
                "Invalid file type '{$extension}'. Allowed types: " . implode(', ', $allowedMimes)
            );
        }

        $maxSize = ($this->config['validation']['max_size'] ?? 10240) * 1024;
        if (filesize($path) > $maxSize) {
            throw new InvalidImageException(
                'File size exceeds the maximum allowed size of ' . 
                ($this->config['validation']['max_size'] ?? 10240) . 'KB.'
            );
        }

        // Validate image dimensions
        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            throw new InvalidImageException('Unable to read image dimensions.');
        }

        [$width, $height] = $imageInfo;
        $minWidth = $this->config['validation']['min_width'] ?? 640;
        $minHeight = $this->config['validation']['min_height'] ?? 480;

        if ($width < $minWidth || $height < $minHeight) {
            throw new InvalidImageException(
                "Image dimensions ({$width}x{$height}) are below the minimum required ({$minWidth}x{$minHeight})."
            );
        }
    }

    /**
     * Prepare image data for upload.
     */
    protected function prepareImageData(UploadedFile|string $image): array
    {
        if ($image instanceof UploadedFile) {
            return [
                'name' => 'imageSource',
                'contents' => fopen($image->getPathname(), 'r'),
                'filename' => $image->getClientOriginalName(),
                'headers' => [
                    'Content-Type' => $image->getMimeType(),
                ],
            ];
        }

        return [
            'name' => 'imageSource',
            'contents' => fopen($image, 'r'),
            'filename' => basename($image),
            'headers' => [
                'Content-Type' => mime_content_type($image),
            ],
        ];
    }

    /**
     * Build request headers.
     * Matches Microblink Cloud API auth: Bearer Base64(api_key:api_secret) when both are set.
     * When only api_key is set, sends Bearer api_key (for pre-built token usage).
     */
    protected function buildHeaders(): array
    {
        $apiKey = $this->config['api_key'] ?? '';
        $apiSecret = $this->config['api_secret'] ?? '';

        if ($apiKey !== '' && $apiSecret !== '') {
            $token = base64_encode($apiKey . ':' . $apiSecret);
        } else {
            $token = $apiKey;
        }

        $headers = [
            'Accept' => 'application/json',
        ];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        // Merge additional headers from config
        if (!empty($this->config['headers'])) {
            $headers = array_merge($headers, $this->config['headers']);
        }

        return $headers;
    }

    /**
     * Send the image upload request to Microblink API.
     *
     * @throws ImageUploadException
     * @throws ApiException
     */
    protected function sendRequest(array $imageData, array $options = []): array
    {
        $endpoint = $options['endpoint'] ?? $this->config['api_endpoint'];

        $this->log('info', 'Sending image upload request', [
            'endpoint' => $endpoint,
            'filename' => $imageData['filename'] ?? 'unknown',
        ]);

        try {
            $response = $this->client->request('POST', $endpoint, [
                'headers' => $this->buildHeaders(),
                'multipart' => [
                    $imageData,
                ],
            ]);

            return $this->handleResponse($response);
        } catch (RequestException $e) {
            $this->log('error', 'Request failed', [
                'message' => $e->getMessage(),
            ]);
            throw new ImageUploadException('Failed to upload image: ' . $e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            $this->log('error', 'Guzzle exception', [
                'message' => $e->getMessage(),
            ]);
            throw new ImageUploadException('HTTP client error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send multi-side image upload request.
     *
     * @throws ImageUploadException
     * @throws ApiException
     */
    protected function sendMultiSideRequest(array $frontData, array $backData, array $options = []): array
    {
        $endpoint = $options['endpoint'] ?? str_replace('blinkid', 'blinkid-multi-side', $this->config['api_endpoint']);

        $frontData['name'] = 'imageFrontSide';
        $backData['name'] = 'imageBackSide';

        $this->log('info', 'Sending multi-side image upload request', [
            'endpoint' => $endpoint,
        ]);

        try {
            $response = $this->client->request('POST', $endpoint, [
                'headers' => $this->buildHeaders(),
                'multipart' => [
                    $frontData,
                    $backData,
                ],
            ]);

            return $this->handleResponse($response);
        } catch (RequestException $e) {
            $this->log('error', 'Multi-side request failed', [
                'message' => $e->getMessage(),
            ]);
            throw new ImageUploadException('Failed to upload images: ' . $e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            $this->log('error', 'Guzzle exception', [
                'message' => $e->getMessage(),
            ]);
            throw new ImageUploadException('HTTP client error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send a JSON request (for base64 images).
     *
     * @throws ImageUploadException
     * @throws ApiException
     */
    protected function sendJsonRequest(array $data, array $options = []): array
    {
        $endpoint = $options['endpoint'] ?? $this->config['api_endpoint'];

        // Determine if this is a multi-side request
        if (isset($data['imageFrontSide']) && isset($data['imageBackSide'])) {
            $endpoint = $options['endpoint'] ?? str_replace('blinkid', 'blinkid-multi-side', $this->config['api_endpoint']);
        }

        $this->log('info', 'Sending JSON request', [
            'endpoint' => $endpoint,
        ]);

        try {
            $response = $this->client->request('POST', $endpoint, [
                'headers' => array_merge($this->buildHeaders(), [
                    'Content-Type' => 'application/json',
                ]),
                'json' => $data,
            ]);

            return $this->handleResponse($response);
        } catch (RequestException $e) {
            $this->log('error', 'JSON request failed', [
                'message' => $e->getMessage(),
            ]);
            throw new ImageUploadException('Failed to upload image: ' . $e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            $this->log('error', 'Guzzle exception', [
                'message' => $e->getMessage(),
            ]);
            throw new ImageUploadException('HTTP client error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract a human-readable error message from the API response body.
     */
    protected function extractErrorMessage(?array $data, string $body): string
    {
        if (is_array($data)) {
            $message = $data['message'] ?? $data['error'] ?? $data['detail'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
            if (isset($data['code']) && is_string($data['code']) && $data['code'] !== '') {
                return $data['code'];
            }
            if (!empty($data['errors']) && is_array($data['errors'])) {
                $parts = [];
                foreach ($data['errors'] as $key => $value) {
                    if (is_string($value)) {
                        $parts[] = $value;
                    } elseif (is_array($value)) {
                        $parts[] = implode(', ', $value);
                    } else {
                        $parts[] = (string) $value;
                    }
                }
                if ($parts !== []) {
                    return implode('; ', array_slice($parts, 0, 5));
                }
            }
            if ($data !== []) {
                $encoded = json_encode($data);
                return 'API returned an error. Response: ' . substr($encoded, 0, 200);
            }
        }

        $trimmed = trim($body);
        if ($trimmed === '') {
            return 'Empty or non-JSON response body';
        }

        return strlen($trimmed) > 200 ? substr($trimmed, 0, 200) . '...' : $trimmed;
    }

    /**
     * Handle the API response.
     *
     * @throws ApiException
     */
    protected function handleResponse(Response $response): array
    {
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        $this->log('info', 'Received API response', [
            'status_code' => $statusCode,
        ]);

        if ($statusCode >= 200 && $statusCode < 300) {
            return $data ?? [];
        }

        $errorMessage = $this->extractErrorMessage($data, $body);

        switch ($statusCode) {
            case 400:
                throw new ApiException("Bad request: {$errorMessage}", $statusCode);
            case 401:
                $hint = 'Check that MICROBLINK_API_KEY and MICROBLINK_API_SECRET are set correctly. Use Cloud API credentials (not a Web SDK license key).';
                throw new ApiException(
                    "Microblink API returned 401 Unauthorized (invalid or missing credentials). {$hint}"
                    . (strlen($errorMessage) > 0 && $errorMessage !== 'Empty or non-JSON response body' ? " API response: {$errorMessage}" : ''),
                    $statusCode
                );
            case 403:
                $hint = 'Set both MICROBLINK_API_KEY and MICROBLINK_API_SECRET (Cloud API credentials from Microblink dashboard, not the BlinkID Web SDK license key).';
                throw new ApiException(
                    "Microblink API returned 403 Forbidden (access denied). {$hint}"
                    . (strlen($errorMessage) > 0 && $errorMessage !== 'Empty or non-JSON response body' ? " API response: {$errorMessage}" : ''),
                    $statusCode
                );
            case 404:
                $hint = 'Check MICROBLINK_API_ENDPOINT and recognizer path (e.g. https://api.microblink.com/v1/recognizers/blinkid or .../passport).';
                throw new ApiException(
                    "Microblink API returned 404 Not Found (wrong URL or endpoint). {$hint}"
                    . (strlen($errorMessage) > 0 && $errorMessage !== 'Empty or non-JSON response body' ? " API response: {$errorMessage}" : ''),
                    $statusCode
                );
            case 429:
                throw new ApiException("Rate limit exceeded: {$errorMessage}", $statusCode);
            case 500:
            case 502:
            case 503:
                throw new ApiException("Server error: {$errorMessage}", $statusCode);
            default:
                throw new ApiException("API error ({$statusCode}): {$errorMessage}", $statusCode);
        }
    }

    /**
     * Log a message if logging is enabled.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->config['logging']['enabled'] ?? false) {
            $channel = $this->config['logging']['channel'] ?? 'stack';
            Log::channel($channel)->$level("[Microblink] {$message}", $context);
        }
    }

    /**
     * Get the current configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Update configuration at runtime.
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        $this->initializeClient();
        return $this;
    }

    /**
     * Set the API key at runtime.
     */
    public function setApiKey(string $apiKey): self
    {
        $this->config['api_key'] = $apiKey;
        return $this;
    }

    /**
     * Set the API endpoint at runtime.
     */
    public function setEndpoint(string $endpoint): self
    {
        $this->config['api_endpoint'] = $endpoint;
        return $this;
    }
}
