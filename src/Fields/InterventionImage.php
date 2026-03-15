<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Fields;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use MoonShine\UI\Fields\Image as MoonShineImage;

final class InterventionImage extends MoonShineImage
{
    protected bool $generateWebp = false;

    protected bool $generateAvif = false;

    protected int $quality = 85;

    protected bool $stripMetadata = false;

    protected ?int $maxWidth = null;

    protected ?int $maxHeight = null;

    protected bool $logging = false;

    protected bool $pngIndexed = false;

    protected int $pngColors = 256;

    protected array $supportedFormats = ['jpeg', 'jpg', 'png', 'gif', 'webp'];

    public function preset(string $name): static
    {
        $presets = config('moonshine-intervention-image.presets', []);
        
        if (! isset($presets[$name])) {
            return $this;
        }

        $preset = $presets[$name];

        if (isset($preset['quality'])) {
            $this->quality($preset['quality']);
        }

        if (isset($preset['generate_webp'])) {
            $this->generateWebp($preset['generate_webp']);
        }

        if (isset($preset['generate_avif'])) {
            $this->generateAvif($preset['generate_avif']);
        }

        if (isset($preset['max_width']) || isset($preset['max_height'])) {
            $this->maxDimensions(
                $preset['max_width'] ?? null,
                $preset['max_height'] ?? null
            );
        }

        if (isset($preset['png_indexed']) && $preset['png_indexed']) {
            $this->pngIndexed(true, $preset['png_colors'] ?? 256);
        }

        return $this;
    }

    public function generateWebp(bool $generate = true): static
    {
        $this->generateWebp = $generate;

        return $this;
    }

    public function generateAvif(bool $generate = true): static
    {
        $this->generateAvif = $generate;

        return $this;
    }

    public function quality(int $quality): static
    {
        $this->quality = max(1, min(100, $quality));

        return $this;
    }

    public function stripMetadata(bool $strip = true): static
    {
        $this->stripMetadata = $strip;

        return $this;
    }

    public function maxDimensions(?int $width, ?int $height = null): static
    {
        $this->maxWidth = $width;
        $this->maxHeight = $height;

        return $this;
    }

    public function logging(bool $enabled = true): static
    {
        $this->logging = $enabled;

        return $this;
    }

    public function pngIndexed(bool $indexed = true, int $colors = 256): static
    {
        $this->pngIndexed = $indexed;
        $this->pngColors = max(2, min(256, $colors));

        return $this;
    }

    protected function resolveOnApply(): ?Closure
    {
        return function (mixed $item) {
            $requestValue = $this->getRequestValue();
            $remainingValues = $this->getRemainingValues();

            data_forget($item, $this->getHiddenRemainingValuesKey());

            $newValue = $this->isMultiple() ? $remainingValues : $remainingValues->first();

            if ($requestValue !== false) {
                if ($this->isMultiple()) {
                    $paths = [];

                    foreach ($requestValue as $file) {
                        $paths[] = $this->storeFile($file);
                    }

                    $newValue = $newValue->merge($paths)
                        ->values()
                        ->unique()
                        ->toArray();
                } else {
                    $newValue = $this->storeFile($requestValue);
                    $this->setRemainingValues([]);
                }
            }

            if ($newValue instanceof Collection) {
                $newValue = $newValue->toArray();
            }

            $this->removeExcludedFiles(
                $this->getCustomName() !== null || $this->isKeepOriginalFileName()
                    ? $newValue
                    : null,
            );

            return data_set($item, $this->getColumn(), $newValue);
        };
    }

    protected function storeFile(UploadedFile $file): string
    {
        $extension = $file->extension();

        if (! $this->isAllowedExtension($extension)) {
            return $file->store($this->getDir(), $this->getOptions());
        }

        if ($this->isKeepOriginalFileName()) {
            $path = $file->storeAs(
                $this->getDir(),
                $file->getClientOriginalName(),
                $this->getOptions(),
            );
        } elseif (! \is_null($this->getCustomName())) {
            $path = $file->storeAs(
                $this->getDir(),
                \call_user_func($this->getCustomName(), $file, $this),
                $this->getOptions(),
            );
        } else {
            $path = $file->store($this->getDir(), $this->getOptions());
        }

        if ($path) {
            $this->processImage($path);
        }

        return $path;
    }

    public function processStoredImage(string $relativePath): void
    {
        $this->processImage($relativePath);
    }

    protected function processImage(string $relativePath): void
    {
        $disk = $this->getDisk();
        $storage = Storage::disk($disk);

        if (! $storage->exists($relativePath)) {
            $this->logError('File not exists in storage', ['path' => $relativePath]);

            return;
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if (! in_array($extension, $this->supportedFormats, true)) {
            return;
        }

        $fullPath = $storage->path($relativePath);

        $this->optimizeImage($fullPath);

        if ($this->generateWebp) {
            $this->generateWebpVersion($fullPath);
        }

        if ($this->generateAvif) {
            $this->generateAvifVersion($fullPath);
        }
    }

    protected function optimizeImage(string $fullPath): void
    {
        $sizeBefore = file_exists($fullPath) ? filesize($fullPath) : 0;

        try {
            $image = Image::read($fullPath);

            if ($this->maxWidth !== null || $this->maxHeight !== null) {
                $image = $image->scaleDown(
                    width: $this->maxWidth,
                    height: $this->maxHeight
                );
            }

            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

            if ($extension === 'png' && $this->pngIndexed) {
                $image->reduceColors($this->pngColors);
            }

            $encoded = match ($extension) {
                'jpg', 'jpeg' => $image->toJpeg(
                    quality: $this->quality,
                    progressive: true,
                    strip: $this->stripMetadata,
                ),
                'png' => $image->toPng(interlaced: true),
                'gif' => $image->toGif(),
                'webp' => $image->toWebp(quality: $this->quality),
                default => null,
            };

            if ($encoded !== null) {
                $encoded->save($fullPath);

                $sizeAfter = file_exists($fullPath) ? filesize($fullPath) : 0;
                $saved = $sizeBefore - $sizeAfter;
                $savedPercent = $sizeBefore > 0 ? round(($saved / $sizeBefore) * 100, 2) : 0;

                $this->logInfo('Optimized', [
                    'path' => $fullPath,
                    'format' => $extension,
                    'before_bytes' => $sizeBefore,
                    'after_bytes' => $sizeAfter,
                    'saved_percent' => $savedPercent,
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('Optimization error', [
                'path' => $fullPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function generateWebpVersion(string $fullPath): void
    {
        $webpPath = $this->getConvertedPath($fullPath, 'webp');

        try {
            $image = Image::read($fullPath);
            $encoded = $image->toWebp(quality: $this->quality);
            $encoded->save($webpPath);

            if (file_exists($webpPath)) {
                $this->logInfo('WebP created', [
                    'path' => $webpPath,
                    'size_bytes' => filesize($webpPath),
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('WebP error', [
                'path' => $fullPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function generateAvifVersion(string $fullPath): void
    {
        $avifPath = $this->getConvertedPath($fullPath, 'avif');

        try {
            $image = Image::read($fullPath);
            $encoded = $image->toAvif(quality: $this->quality);
            $encoded->save($avifPath);

            if (file_exists($avifPath)) {
                $this->logInfo('AVIF created', [
                    'path' => $avifPath,
                    'size_bytes' => filesize($avifPath),
                ]);
            }
        } catch (\Exception $e) {
            $this->logError('AVIF error', [
                'path' => $fullPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getConvertedPath(string $originalPath, string $format): string
    {
        $info = pathinfo($originalPath);

        return $info['dirname'].'/'.$info['filename'].'.'.$format;
    }

    public function deleteFile(string $fileName): bool
    {
        $this->deleteConvertedVersions($fileName);

        return parent::deleteFile($fileName);
    }

    protected function deleteConvertedVersions(string $fileName): void
    {
        $disk = $this->getDisk();
        $fullPath = $this->getPrependedDir($fileName);

        $info = pathinfo($fullPath);
        $basePath = $info['dirname'].'/'.$info['filename'];

        foreach (['webp', 'avif'] as $format) {
            $conversionPath = $basePath.'.'.$format;

            if ($this->getCore()->getStorage(disk: $disk)->exists($conversionPath)) {
                $this->getCore()->getStorage(disk: $disk)->delete($conversionPath);
            }
        }
    }

    public function getWebpPath(string $fileName): ?string
    {
        $info = pathinfo($fileName);
        $webpPath = $info['dirname'].'/'.$info['filename'].'.webp';
        $fullPath = $this->getPrependedDir($webpPath);

        if ($this->getCore()->getStorage(disk: $this->getDisk())->exists($fullPath)) {
            return $this->getPath($fullPath);
        }

        return null;
    }

    public function getAvifPath(string $fileName): ?string
    {
        $info = pathinfo($fileName);
        $avifPath = $info['dirname'].'/'.$info['filename'].'.avif';
        $fullPath = $this->getPrependedDir($avifPath);

        if ($this->getCore()->getStorage(disk: $this->getDisk())->exists($fullPath)) {
            return $this->getPath($fullPath);
        }

        return null;
    }

    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->logging) {
            Log::info('[InterventionImage] '.$message, $context);
        }
    }

    protected function logError(string $message, array $context = []): void
    {
        if ($this->logging) {
            Log::error('[InterventionImage] '.$message, $context);
        }
    }
}
