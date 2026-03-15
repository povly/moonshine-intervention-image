<?php

return [
    'default' => [
        'quality' => env('MOONSHINE_IMAGE_QUALITY', 85),
        'generate_webp' => env('MOONSHINE_IMAGE_WEBP', false),
        'generate_avif' => env('MOONSHINE_IMAGE_AVIF', false),
        'strip_metadata' => env('MOONSHINE_IMAGE_STRIP_METADATA', false),
        'max_width' => env('MOONSHINE_IMAGE_MAX_WIDTH', null),
        'max_height' => env('MOONSHINE_IMAGE_MAX_HEIGHT', null),
        'logging' => env('MOONSHINE_IMAGE_LOGGING', false),
    ],

    'png' => [
        'indexed' => env('MOONSHINE_IMAGE_PNG_INDEXED', true),
        'colors' => env('MOONSHINE_IMAGE_PNG_COLORS', 256),
    ],

    'presets' => [
        'banner' => [
            'quality' => 85,
            'generate_webp' => true,
            'generate_avif' => false,
            'max_width' => 1920,
            'max_height' => 1080,
            'png_indexed' => true,
        ],
        'thumbnail' => [
            'quality' => 80,
            'generate_webp' => true,
            'generate_avif' => false,
            'max_width' => 400,
            'max_height' => 400,
            'png_indexed' => true,
        ],
        'gallery' => [
            'quality' => 85,
            'generate_webp' => true,
            'generate_avif' => true,
            'max_width' => 1920,
            'max_height' => 1080,
            'png_indexed' => true,
        ],
    ],
];
