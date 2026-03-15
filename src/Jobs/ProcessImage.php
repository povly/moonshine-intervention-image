<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;

class ProcessImage implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $maxExceptions = 3;

    protected string $relativePath;

    protected string $disk;

    protected array $options;

    public function __construct(
        string $relativePath,
        string $disk = 'public',
        array $options = []
    ) {
        $this->relativePath = $relativePath;
        $this->disk = $disk;

        $this->options = array_merge([
            'quality' => 85,
            'quality_webp' => 80,
            'quality_avif' => 65,
            'generate_webp' => false,
            'generate_avif' => false,
            'strip_metadata' => false,
            'max_width' => null,
            'max_height' => null,
            'png_indexed' => false,
            'png_colors' => 256,
            'logging' => false,
        ], $options);

        $this->tries = config('moonshine-intervention-image.queue.tries', 3);
        $this->timeout = config('moonshine-intervention-image.queue.timeout', 120);
    }

    public function handle(): void
    {
        $storage = Storage::disk($this->disk);

        if (! $storage->exists($this->relativePath)) {
            $this->logError('File not exists in storage', ['path' => $this->relativePath]);

            return;
        }

        $extension = strtolower(pathinfo($this->relativePath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'webp'], true)) {
            $this->logInfo('Skipping unsupported format', ['path' => $this->relativePath, 'format' => $extension]);

            return;
        }

        $fullPath = $storage->path($this->relativePath);

        $this->logInfo('Processing started', [
            'path' => $this->relativePath,
            'disk' => $this->disk,
            'attempt' => $this->attempts(),
        ]);

        $this->optimizeImage($fullPath);

        if ($this->options['generate_webp']) {
            $this->generateWebpVersion($fullPath);
        }

        if ($this->options['generate_avif']) {
            $this->generateAvifVersion($fullPath);
        }

        $this->logInfo('Processing completed', ['path' => $this->relativePath]);
    }

    protected function optimizeImage(string $fullPath): void
    {
        $sizeBefore = file_exists($fullPath) ? filesize($fullPath) : 0;

        try {
            $image = Image::read($fullPath);

            if ($this->options['max_width'] !== null || $this->options['max_height'] !== null) {
                $image = $image->scaleDown(
                    width: $this->options['max_width'],
                    height: $this->options['max_height']
                );
            }

            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

            if ($extension === 'png' && $this->options['png_indexed']) {
                $image->reduceColors($this->options['png_colors']);
            }

            $encoded = match ($extension) {
                'jpg', 'jpeg' => $image->toJpeg(
                    quality: $this->options['quality'],
                    progressive: true,
                    strip: $this->options['strip_metadata'],
                ),
                'png' => $image->toPng(interlaced: true),
                'gif' => $image->toGif(),
                'webp' => $image->toWebp(quality: $this->options['quality']),
                default => null,
            };

            if ($encoded !== null) {
                $encoded->save($fullPath);

                $sizeAfter = file_exists($fullPath) ? filesize($fullPath) : 0;
                $saved = $sizeBefore - $sizeAfter;
                $savedPercent = $sizeBefore > 0 ? round(($saved / $sizeBefore) * 100, 2) : 0;

                $this->logInfo('Image optimized', [
                    'path' => $fullPath,
                    'format' => $extension,
                    'before_bytes' => $sizeBefore,
                    'after_bytes' => $sizeAfter,
                    'saved_percent' => $savedPercent,
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('Optimization failed', [
                'path' => $fullPath,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Image optimization failed: {$e->getMessage()}", 0, $e);
        }
    }

    protected function generateWebpVersion(string $fullPath): void
    {
        $webpPath = $this->getConvertedPath($fullPath, 'webp');

        try {
            $image = Image::read($fullPath);
            $encoded = $image->toWebp(quality: $this->options['quality_webp']);
            $encoded->save($webpPath);

            if (file_exists($webpPath)) {
                $this->logInfo('WebP created', [
                    'path' => $webpPath,
                    'size_bytes' => filesize($webpPath),
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('WebP conversion failed', [
                'path' => $fullPath,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("WebP conversion failed: {$e->getMessage()}", 0, $e);
        }
    }

    protected function generateAvifVersion(string $fullPath): void
    {
        $avifPath = $this->getConvertedPath($fullPath, 'avif');

        try {
            $image = Image::read($fullPath);
            $encoded = $image->toAvif(quality: $this->options['quality_avif']);
            $encoded->save($avifPath);

            if (file_exists($avifPath)) {
                $this->logInfo('AVIF created', [
                    'path' => $avifPath,
                    'size_bytes' => filesize($avifPath),
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('AVIF conversion failed', [
                'path' => $fullPath,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("AVIF conversion failed: {$e->getMessage()}", 0, $e);
        }
    }

    protected function getConvertedPath(string $originalPath, string $format): string
    {
        $info = pathinfo($originalPath);

        return $info['dirname'].'/'.$info['filename'].'.'.$format;
    }

    public function failed(\Throwable $exception): void
    {
        $this->logError('Job failed permanently', [
            'path' => $this->relativePath,
            'disk' => $this->disk,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(1);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->options['logging']) {
            Log::info('[InterventionImage] '.$message, $context);
        }
    }

    protected function logError(string $message, array $context = []): void
    {
        if ($this->options['logging']) {
            Log::error('[InterventionImage] '.$message, $context);
        }
    }

    public function displayName(): string
    {
        return "ProcessImage: {$this->relativePath}";
    }

    public function tags(): array
    {
        return [
            'moonshine-intervention-image',
            'disk:'.$this->disk,
            'path:'.$this->relativePath,
        ];
    }
}
