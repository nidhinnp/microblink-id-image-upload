<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Microblink API Key
    |--------------------------------------------------------------------------
    |
    | Your Microblink API key for authentication. This key is required
    | for all API requests. You can obtain this from your Microblink
    | dashboard at https://microblink.com
    |
    */

    'api_key' => env('MICROBLINK_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Microblink API Secret (optional, for Cloud API)
    |--------------------------------------------------------------------------
    |
    | When set, the package builds the Bearer token as Base64(api_key:api_secret)
    | per Microblink Cloud API docs. Leave empty if you use a pre-built token
    | in api_key. Required for Cloud API authentication.
    |
    */

    'api_secret' => env('MICROBLINK_API_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Microblink API Endpoint (default)
    |--------------------------------------------------------------------------
    |
    | Default endpoint used by upload() when no endpoint is specified.
    | Use this for generic single-image recognition (BlinkID).
    |
    */

    'api_endpoint' => env('MICROBLINK_API_ENDPOINT', 'https://api.microblink.com/v1/recognizers/blinkid'),

    /*
    |--------------------------------------------------------------------------
    | Document-specific Endpoints (optional)
    |--------------------------------------------------------------------------
    |
    | Microblink uses different recognizer URLs per use case:
    | - passport: MRZ on passports only
    | - blinkid: front side of supported docs (national ID, passport, DL, etc.)
    | - blinkid_multi_side: front + back (e.g. national ID card)
    |
    | If set, uploadForPassport() / uploadForNationalId() use these; otherwise
    | they fall back to api_endpoint and the multi-side variant.
    |
    */

    'endpoints' => [
        'passport' => env('MICROBLINK_PASSPORT_ENDPOINT', 'https://api.microblink.com/v1/recognizers/passport'),
        'blinkid' => env('MICROBLINK_BLINKID_ENDPOINT', 'https://api.microblink.com/v1/recognizers/blinkid'),
        'blinkid_multi_side' => env('MICROBLINK_BLINKID_MULTI_SIDE_ENDPOINT', 'https://api.microblink.com/v1/recognizers/blinkid-multi-side'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds to wait for a response from the
    | Microblink API. Increase this value if you're experiencing
    | timeout issues with larger images.
    |
    */

    'timeout' => env('MICROBLINK_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Connection Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds to wait while trying to connect
    | to the Microblink API server.
    |
    */

    'connect_timeout' => env('MICROBLINK_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic retry behavior for failed requests.
    |
    */

    'retry' => [
        'enabled' => env('MICROBLINK_RETRY_ENABLED', true),
        'times' => env('MICROBLINK_RETRY_TIMES', 3),
        'sleep' => env('MICROBLINK_RETRY_SLEEP', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Validation
    |--------------------------------------------------------------------------
    |
    | Configure image validation rules for uploaded files.
    |
    */

    'validation' => [
        'allowed_mimes' => ['jpeg', 'jpg', 'png', 'gif', 'bmp', 'webp'],
        'max_size' => 10240, // KB (10MB)
        'min_width' => 640,
        'min_height' => 480,
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Headers
    |--------------------------------------------------------------------------
    |
    | Any additional headers to include with API requests.
    |
    */

    'headers' => [
        // 'X-Custom-Header' => 'value',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable logging of API requests and responses.
    | Useful for debugging but should be disabled in production.
    |
    */

    'logging' => [
        'enabled' => env('MICROBLINK_LOGGING_ENABLED', false),
        'channel' => env('MICROBLINK_LOG_CHANNEL', 'stack'),
    ],

];
