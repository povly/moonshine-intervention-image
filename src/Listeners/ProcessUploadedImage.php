<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Listeners;

use Illuminate\Support\Facades\Storage;
use Povly\MoonshineInterventionImage\Services\ImageProcessor;
use Povly\MoonshineInterventionImage\ValueObjects\ImageProcessingConfig;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileUploaded;

final class ProcessUploadedImage
{
    protected array $imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    public function handle(MediaManagerFileUploaded $event): void
    {
        $extension = strtolower(pathinfo($event->path, PATHINFO_EXTENSION));

        if (! in_array($extension, $this->imageExtensions, true)) {
            return;
        }

        $storage = Storage::disk($event->disk);
        $fullPath = $storage->path($event->path);

        if (! file_exists($fullPath)) {
            return;
        }

        $config = $this->createConfig();

        try {
            $processor = new ImageProcessor($config);
            $processor->process($fullPath);
        } catch (\Exception $e) {
        }
    }

    protected function createConfig(): ImageProcessingConfig
    {
        $defaultConfig = config('moonshine-intervention-image.default', []);
        $pngConfig = config('moonshine-intervention-image.png', []);
        $watermarkConfig = config('moonshine-intervention-image.watermark', []);
        $watermarkTextConfig = config('moonshine-intervention-image.watermark_text', []);

        return ImageProcessingConfig::fromArray([
            'quality' => $defaultConfig['quality'] ?? 85,
            'quality_webp' => $defaultConfig['quality_webp'] ?? 80,
            'quality_avif' => $defaultConfig['quality_avif'] ?? 65,
            'generate_webp' => $defaultConfig['generate_webp'] ?? false,
            'generate_avif' => $defaultConfig['generate_avif'] ?? false,
            'strip_metadata' => $defaultConfig['strip_metadata'] ?? false,
            'max_width' => $defaultConfig['max_width'] ?? null,
            'max_height' => $defaultConfig['max_height'] ?? null,
            'png_indexed' => $pngConfig['indexed'] ?? false,
            'png_colors' => $pngConfig['colors'] ?? 256,
            'logging' => $defaultConfig['logging'] ?? false,
            'watermark' => ($watermarkConfig['enabled'] ?? false) ? $watermarkConfig : null,
            'watermark_text' => ($watermarkTextConfig['enabled'] ?? false) ? $watermarkTextConfig : null,
        ]);
    }
}
