<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Image Processing Settings
    |--------------------------------------------------------------------------
    */

    'default' => [
        'quality' => env('MOONSHINE_INTERVENTION_IMAGE_QUALITY', 85),
        'quality_webp' => env('MOONSHINE_INTERVENTION_IMAGE_QUALITY_WEBP', 80),
        'quality_avif' => env('MOONSHINE_INTERVENTION_IMAGE_QUALITY_AVIF', 65),
        'generate_webp' => env('MOONSHINE_INTERVENTION_IMAGE_WEBP', false),
        'generate_avif' => env('MOONSHINE_INTERVENTION_IMAGE_AVIF', false),
        'strip_metadata' => env('MOONSHINE_INTERVENTION_IMAGE_STRIP_METADATA', false),
        'max_width' => env('MOONSHINE_INTERVENTION_IMAGE_MAX_WIDTH'),
        'max_height' => env('MOONSHINE_INTERVENTION_IMAGE_MAX_HEIGHT'),
        'logging' => env('MOONSHINE_INTERVENTION_IMAGE_LOGGING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | PNG Optimization Settings
    |--------------------------------------------------------------------------
    */

    'png' => [
        'indexed' => env('MOONSHINE_INTERVENTION_IMAGE_PNG_INDEXED', true),
        'colors' => env('MOONSHINE_INTERVENTION_IMAGE_PNG_COLORS', 256),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | Enable queue processing for image optimization and conversion.
    | Useful for handling large images or multiple uploads without
    | blocking the user request.
    |
    | enabled: Enable/disable queue processing globally
    | connection: Queue connection (null = default)
    | queue: Queue name to use
    | delay: Delay before processing (seconds or Carbon instance)
    | tries: Number of attempts before failing
    | timeout: Maximum time in seconds for job execution
    |
    */

    'queue' => [
        'enabled' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_ENABLED', false),
        'connection' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_CONNECTION'),
        'queue' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_NAME', 'images'),
        'delay' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_DELAY'),
        'tries' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_TRIES', 3),
        'timeout' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Presets
    |--------------------------------------------------------------------------
    |
    | Define reusable presets for common use cases.
    | Apply with: InterventionImage::make('Image')->preset('banner')
    |
    */

    'presets' => [
        'banner' => [
            'quality' => 85,
            'quality_webp' => 80,
            'quality_avif' => 65,
            'generate_webp' => true,
            'generate_avif' => false,
            'max_width' => 1920,
            'max_height' => 1080,
            'png_indexed' => true,
        ],
        'thumbnail' => [
            'quality' => 80,
            'quality_webp' => 75,
            'quality_avif' => 60,
            'generate_webp' => true,
            'generate_avif' => false,
            'max_width' => 400,
            'max_height' => 400,
            'png_indexed' => true,
        ],
        'gallery' => [
            'quality' => 85,
            'quality_webp' => 80,
            'quality_avif' => 65,
            'generate_webp' => true,
            'generate_avif' => true,
            'max_width' => 1920,
            'max_height' => 1080,
            'png_indexed' => true,
        ],
    ],
];
