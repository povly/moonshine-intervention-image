<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Fields;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use MoonShine\UI\Fields\Image as MoonShineImage;
use Povly\MoonshineInterventionImage\Enums\WatermarkPosition;
use Povly\MoonshineInterventionImage\Jobs\ProcessImage;

final class InterventionImage extends MoonShineImage
{
    protected bool $generateWebp = false;

    protected bool $generateAvif = false;

    protected int $quality = 85;

    protected int $qualityWebp = 80;

    protected int $qualityAvif = 65;

    protected bool $stripMetadata = false;

    protected ?int $maxWidth = null;

    protected ?int $maxHeight = null;

    protected ?bool $logging = null;

    protected bool $pngIndexed = false;

    protected int $pngColors = 256;

    protected array $supportedFormats = ['jpeg', 'jpg', 'png', 'gif', 'webp'];

    protected ?bool $queueEnabled = null;

    protected ?string $queueConnection = null;

    protected ?string $queueName = null;

    protected DateTimeInterface|DateInterval|int|null $queueDelay = null;

    protected ?string $watermarkImage = null;

    protected ?WatermarkPosition $watermarkPosition = null;

    protected ?int $watermarkOffsetX = null;

    protected ?int $watermarkOffsetY = null;

    protected ?int $watermarkOpacity = null;

    protected ?int $watermarkWidth = null;

    protected ?int $watermarkHeight = null;

    protected ?string $watermarkText = null;

    protected ?string $watermarkTextFont = null;

    protected ?int $watermarkTextSize = null;

    protected ?string $watermarkTextColor = null;

    protected ?WatermarkPosition $watermarkTextPosition = null;

    protected ?int $watermarkTextOffsetX = null;

    protected ?int $watermarkTextOffsetY = null;

    protected ?string $watermarkTextStrokeColor = null;

    protected ?int $watermarkTextStrokeWidth = null;

    protected ?int $watermarkTextAngle = null;

    protected bool $watermarkDisabled = false;

    protected ?int $customPositionX = null;

    protected ?int $customPositionY = null;

    protected ?int $customTextPositionX = null;

    protected ?int $customTextPositionY = null;

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

        if (isset($preset['quality_webp'])) {
            $this->qualityWebp($preset['quality_webp']);
        }

        if (isset($preset['quality_avif'])) {
            $this->qualityAvif($preset['quality_avif']);
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

        if (isset($preset['logging'])) {
            $this->logging($preset['logging']);
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

    public function qualityWebp(int $quality): static
    {
        $this->qualityWebp = max(1, min(100, $quality));

        return $this;
    }

    public function qualityAvif(int $quality): static
    {
        $this->qualityAvif = max(1, min(100, $quality));

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

    public function queue(
        bool $enabled = true,
        ?string $connection = null,
        ?string $queue = null,
        DateTimeInterface|DateInterval|int|null $delay = null
    ): static {
        $this->queueEnabled = $enabled;
        $this->queueConnection = $connection;
        $this->queueName = $queue;
        $this->queueDelay = $delay;

        return $this;
    }

    public function queueDelay(DateTimeInterface|DateInterval|int|null $delay): static
    {
        $this->queueDelay = $delay;

        return $this;
    }

    public function watermarkImage(
        ?string $path = null,
        WatermarkPosition|string|null $position = null,
        int $offsetX = 10,
        int $offsetY = 10,
        int $opacity = 100,
        ?int $width = null,
        ?int $height = null,
        ?int $customX = null,
        ?int $customY = null
    ): static {
        if ($path !== null) {
            $this->watermarkImage = $path;
        }

        if ($position !== null) {
            if (is_string($position)) {
                $this->watermarkPosition = WatermarkPosition::tryFrom($position) ?? WatermarkPosition::BottomRight;
            } else {
                $this->watermarkPosition = $position;
            }
        }

        $this->watermarkOffsetX = $offsetX;
        $this->watermarkOffsetY = $offsetY;
        $this->watermarkOpacity = max(0, min(100, $opacity));
        $this->watermarkWidth = $width;
        $this->watermarkHeight = $height;
        $this->customPositionX = $customX;
        $this->customPositionY = $customY;

        return $this;
    }

    public function watermarkText(
        ?string $text = null,
        WatermarkPosition|string|null $position = null,
        int $offsetX = 10,
        int $offsetY = 10,
        int $size = 24,
        string $color = 'ffffff',
        ?int $customX = null,
        ?int $customY = null
    ): static {
        if ($text !== null) {
            $this->watermarkText = $text;
        }

        if ($position !== null) {
            if (is_string($position)) {
                $this->watermarkTextPosition = WatermarkPosition::tryFrom($position) ?? WatermarkPosition::BottomRight;
            } else {
                $this->watermarkTextPosition = $position;
            }
        }

        $this->watermarkTextOffsetX = $offsetX;
        $this->watermarkTextOffsetY = $offsetY;
        $this->watermarkTextSize = $size;
        $this->watermarkTextColor = $color;
        $this->customTextPositionX = $customX;
        $this->customTextPositionY = $customY;

        return $this;
    }

    public function watermarkTextFont(?string $fontPath): static
    {
        $this->watermarkTextFont = $fontPath;

        return $this;
    }

    public function watermarkTextStroke(?string $color = '000000', int $width = 2): static
    {
        $this->watermarkTextStrokeColor = $color;
        $this->watermarkTextStrokeWidth = $width;

        return $this;
    }

    public function watermarkTextAngle(?int $angle): static
    {
        $this->watermarkTextAngle = $angle;

        return $this;
    }

    public function disableWatermark(bool $disable = true): static
    {
        $this->watermarkDisabled = $disable;

        return $this;
    }

    protected function hasWatermark(): bool
    {
        if ($this->watermarkDisabled) {
            return false;
        }

        $globalImageEnabled = config('moonshine-intervention-image.watermark.enabled', false);
        $globalTextEnabled = config('moonshine-intervention-image.watermark_text.enabled', false);

        return $this->watermarkImage !== null
            || $this->watermarkText !== null
            || $globalImageEnabled
            || $globalTextEnabled;
    }

    protected function getWatermarkOptions(): ?array
    {
        if ($this->watermarkDisabled) {
            return null;
        }

        if ($this->watermarkImage !== null) {
            return [
                'image' => $this->watermarkImage,
                'position' => $this->watermarkPosition?->value ?? 'bottom-right',
                'offset_x' => $this->watermarkOffsetX ?? 10,
                'offset_y' => $this->watermarkOffsetY ?? 10,
                'opacity' => $this->watermarkOpacity ?? 100,
                'width' => $this->watermarkWidth,
                'height' => $this->watermarkHeight,
                'custom_x' => $this->customPositionX,
                'custom_y' => $this->customPositionY,
            ];
        }

        $globalConfig = config('moonshine-intervention-image.watermark', []);

        if (($globalConfig['enabled'] ?? false) && ($globalConfig['image'] ?? null)) {
            $position = $this->watermarkPosition?->value ?? ($globalConfig['position'] ?? 'bottom-right');
            $customX = $this->customPositionX ?? $globalConfig['custom_position']['x'] ?? null;
            $customY = $this->customPositionY ?? $globalConfig['custom_position']['y'] ?? null;

            return [
                'image' => $globalConfig['image'],
                'position' => $position,
                'offset_x' => $this->watermarkOffsetX ?? ($globalConfig['offset_x'] ?? 10),
                'offset_y' => $this->watermarkOffsetY ?? ($globalConfig['offset_y'] ?? 10),
                'opacity' => $this->watermarkOpacity ?? ($globalConfig['opacity'] ?? 100),
                'width' => $this->watermarkWidth ?? ($globalConfig['width'] ?? null),
                'height' => $this->watermarkHeight ?? ($globalConfig['height'] ?? null),
                'custom_x' => $customX,
                'custom_y' => $customY,
            ];
        }

        return null;
    }

    protected function getWatermarkTextOptions(): ?array
    {
        if ($this->watermarkDisabled) {
            return null;
        }

        if ($this->watermarkText !== null) {
            return [
                'text' => $this->watermarkText,
                'font' => $this->watermarkTextFont,
                'size' => $this->watermarkTextSize ?? 24,
                'color' => $this->watermarkTextColor ?? 'ffffff',
                'position' => $this->watermarkTextPosition?->value ?? 'bottom-right',
                'offset_x' => $this->watermarkTextOffsetX ?? 10,
                'offset_y' => $this->watermarkTextOffsetY ?? 10,
                'stroke_color' => $this->watermarkTextStrokeColor,
                'stroke_width' => $this->watermarkTextStrokeWidth ?? 0,
                'angle' => $this->watermarkTextAngle,
                'custom_x' => $this->customTextPositionX,
                'custom_y' => $this->customTextPositionY,
            ];
        }

        $globalConfig = config('moonshine-intervention-image.watermark_text', []);

        if (($globalConfig['enabled'] ?? false) && ($globalConfig['text'] ?? null)) {
            $position = $this->watermarkTextPosition?->value ?? ($globalConfig['position'] ?? 'bottom-right');
            $customX = $this->customTextPositionX ?? $globalConfig['custom_position']['x'] ?? null;
            $customY = $this->customTextPositionY ?? $globalConfig['custom_position']['y'] ?? null;

            return [
                'text' => $globalConfig['text'],
                'font' => $this->watermarkTextFont ?? $globalConfig['font'] ?? null,
                'size' => $this->watermarkTextSize ?? ($globalConfig['size'] ?? 24),
                'color' => $this->watermarkTextColor ?? ($globalConfig['color'] ?? 'ffffff'),
                'position' => $position,
                'offset_x' => $this->watermarkTextOffsetX ?? ($globalConfig['offset_x'] ?? 10),
                'offset_y' => $this->watermarkTextOffsetY ?? ($globalConfig['offset_y'] ?? 10),
                'stroke_color' => $this->watermarkTextStrokeColor ?? $globalConfig['stroke_color'] ?? null,
                'stroke_width' => $this->watermarkTextStrokeWidth ?? ($globalConfig['stroke_width'] ?? 0),
                'angle' => $this->watermarkTextAngle ?? $globalConfig['angle'] ?? null,
                'custom_x' => $customX,
                'custom_y' => $customY,
            ];
        }

        return null;
    }

    protected function isQueueEnabled(): bool
    {
        if ($this->queueEnabled !== null) {
            return $this->queueEnabled;
        }

        return config('moonshine-intervention-image.queue.enabled', false);
    }

    protected function isLoggingEnabled(): bool
    {
        if ($this->logging !== null) {
            return $this->logging;
        }

        return config('moonshine-intervention-image.default.logging', false);
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

        if ($this->isQueueEnabled()) {
            $this->dispatchToQueue($relativePath, $disk);
        } else {
            $this->processImageSync($relativePath, $disk);
        }
    }

    protected function dispatchToQueue(string $relativePath, string $disk): void
    {
        $job = new ProcessImage($relativePath, $disk, [
            'quality' => $this->quality,
            'quality_webp' => $this->qualityWebp,
            'quality_avif' => $this->qualityAvif,
            'generate_webp' => $this->generateWebp,
            'generate_avif' => $this->generateAvif,
            'strip_metadata' => $this->stripMetadata,
            'max_width' => $this->maxWidth,
            'max_height' => $this->maxHeight,
            'png_indexed' => $this->pngIndexed,
            'png_colors' => $this->pngColors,
            'logging' => $this->isLoggingEnabled(),
            'watermark' => $this->getWatermarkOptions(),
            'watermark_text' => $this->getWatermarkTextOptions(),
        ]);

        $connection = $this->queueConnection ?? config('moonshine-intervention-image.queue.connection');
        $queue = $this->queueName ?? config('moonshine-intervention-image.queue.queue', 'default');
        $delay = $this->queueDelay ?? config('moonshine-intervention-image.queue.delay');

        if ($connection) {
            $job->onConnection($connection);
        }

        $job->onQueue($queue);

        if ($delay !== null) {
            $job->delay($delay);
        }

        dispatch($job);

        $this->logInfo('Job dispatched', [
            'path' => $relativePath,
            'queue' => $queue,
            'connection' => $connection,
            'delay' => $delay,
        ]);
    }

    protected function processImageSync(string $relativePath, string $disk): void
    {
        $storage = Storage::disk($disk);
        $fullPath = $storage->path($relativePath);

        $this->optimizeImage($fullPath);

        if ($this->generateWebp) {
            $this->generateWebpVersion($fullPath);
        }

        if ($this->generateAvif) {
            $this->generateAvifVersion($fullPath);
        }
    }

    protected function applyWatermarks(ImageInterface $image, string $basePath): ImageInterface
    {
        if ($this->watermarkDisabled) {
            return $image;
        }

        $watermarkOptions = $this->getWatermarkOptions();
        if ($watermarkOptions !== null && file_exists($watermarkOptions['image'])) {
            $this->applyImageWatermark($image, $watermarkOptions);
        }

        $watermarkTextOptions = $this->getWatermarkTextOptions();
        if ($watermarkTextOptions !== null) {
            $this->applyTextWatermark($image, $basePath, $watermarkTextOptions);
        }

        return $image;
    }

    protected function applyImageWatermark(ImageInterface $image, array $options): void
    {
        try {
            $watermarkPath = $options['image'];
            $position = $options['position'] ?? 'bottom-right';
            $offsetX = $options['offset_x'] ?? 10;
            $offsetY = $options['offset_y'] ?? 10;
            $opacity = $options['opacity'] ?? 100;
            $width = $options['width'] ?? null;
            $height = $options['height'] ?? null;

            $watermark = Image::read($watermarkPath);

            if ($width !== null || $height !== null) {
                $watermark = $watermark->resize($width, $height);
            }

            if ($position === 'custom' && $options['custom_x'] !== null && $options['custom_y'] !== null) {
                $image->place(
                    $watermark,
                    'top-left',
                    $options['custom_x'],
                    $options['custom_y'],
                    $opacity
                );
            } else {
                $image->place(
                    $watermark,
                    $position,
                    $offsetX,
                    $offsetY,
                    $opacity
                );
            }

            $this->logInfo('Image watermark applied', [
                'watermark' => $watermarkPath,
                'position' => $position,
                'opacity' => $opacity,
                'width' => $width,
                'height' => $height,
            ]);
        } catch (\Exception $e) {
            $this->logError('Image watermark error', [
                'watermark' => $options['image'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function applyTextWatermark(ImageInterface $image, string $basePath, array $options): void
    {
        try {
            $imageWidth = $image->width();
            $imageHeight = $image->height();
            $position = $options['position'];

            if ($position === 'custom' && $options['custom_x'] !== null && $options['custom_y'] !== null) {
                $x = $options['custom_x'];
                $y = $options['custom_y'];
                $align = 'left';
                $valign = 'top';
            } else {
                [$x, $y, $align, $valign] = $this->calculateTextPosition(
                    $imageWidth,
                    $imageHeight,
                    $options['size'],
                    $position,
                    $options['offset_x'],
                    $options['offset_y']
                );
            }

            $image->text($options['text'], $x, $y, function ($font) use ($basePath, $options, $align, $valign) {
                $fontPath = $options['font'] ?? null;

                if ($fontPath !== null && file_exists($fontPath)) {
                    $font->filename($fontPath);
                } elseif ($fontPath !== null) {
                    $relativeFontPath = dirname($basePath).'/'.$fontPath;
                    if (file_exists($relativeFontPath)) {
                        $font->filename($relativeFontPath);
                    }
                }

                $font->size($options['size']);
                $font->color($options['color']);
                $font->align($align);
                $font->valign($valign);

                $strokeColor = $options['stroke_color'] ?? null;
                $strokeWidth = $options['stroke_width'] ?? 0;

                if ($strokeColor !== null && $strokeWidth > 0) {
                    $font->stroke($strokeColor, $strokeWidth);
                }

                $angle = $options['angle'] ?? null;
                if ($angle !== null) {
                    $font->angle($angle);
                }
            });

            $this->logInfo('Text watermark applied', [
                'text' => $options['text'],
                'position' => $options['position'],
                'offset_x' => $options['offset_x'],
                'offset_y' => $options['offset_y'],
                'x' => $x,
                'y' => $y,
            ]);
        } catch (\Exception $e) {
            $this->logError('Text watermark error', [
                'text' => $options['text'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function calculateTextPosition(
        int $imageWidth,
        int $imageHeight,
        int $fontSize,
        string $position,
        int $offsetX,
        int $offsetY
    ): array {
        $padding = 10;

        return match ($position) {
            'top-left' => [$padding + $offsetX, $padding + $offsetY, 'left', 'top'],
            'top' => [$imageWidth / 2 + $offsetX, $padding + $offsetY, 'center', 'top'],
            'top-right' => [$imageWidth - $padding + $offsetX, $padding + $offsetY, 'right', 'top'],
            'left' => [$padding + $offsetX, $imageHeight / 2 + $offsetY, 'left', 'middle'],
            'center' => [$imageWidth / 2 + $offsetX, $imageHeight / 2 + $offsetY, 'center', 'middle'],
            'right' => [$imageWidth - $padding + $offsetX, $imageHeight / 2 + $offsetY, 'right', 'middle'],
            'bottom-left' => [$padding + $offsetX, $imageHeight - $padding + $offsetY, 'left', 'bottom'],
            'bottom' => [$imageWidth / 2 + $offsetX, $imageHeight - $padding + $offsetY, 'center', 'bottom'],
            'bottom-right' => [$imageWidth - $padding + $offsetX, $imageHeight - $padding + $offsetY, 'right', 'bottom'],
            default => [$imageWidth - $padding + $offsetX, $imageHeight - $padding + $offsetY, 'right', 'bottom'],
        };
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

            $this->applyWatermarks($image, $fullPath);

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
            $encoded = $image->toWebp(quality: $this->qualityWebp);
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
            $encoded = $image->toAvif(quality: $this->qualityAvif);
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
        if ($this->isLoggingEnabled()) {
            Log::info('[InterventionImage] '.$message, $context);
        }
    }

    protected function logError(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled()) {
            Log::error('[InterventionImage] '.$message, $context);
        }
    }
}
