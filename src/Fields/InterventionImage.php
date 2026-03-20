<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Fields;

use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use MoonShine\UI\Fields\Image as MoonShineImage;
use Povly\MoonshineInterventionImage\Enums\WatermarkPosition;
use Povly\MoonshineInterventionImage\Jobs\ProcessImage;
use Povly\MoonshineInterventionImage\Services\ImageProcessor;
use Povly\MoonshineInterventionImage\Support\PathHelper;
use Povly\MoonshineInterventionImage\ValueObjects\ImageProcessingConfig;
use Povly\MoonshineInterventionImage\ValueObjects\TextWatermarkConfig;
use Povly\MoonshineInterventionImage\ValueObjects\WatermarkConfig;

final class InterventionImage extends MoonShineImage
{
    protected string $view = 'moonshine-intervention-image::fields.intervention-image';

    protected ?bool $generateWebp = null;

    protected ?bool $generateAvif = null;

    protected ?int $quality = null;

    protected ?int $qualityWebp = null;

    protected ?int $qualityAvif = null;

    protected ?bool $stripMetadata = null;

    protected ?int $maxWidth = null;

    protected ?int $maxHeight = null;

    protected ?bool $logging = null;

    protected ?bool $pngIndexed = null;

    protected ?int $pngColors = null;

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
        ?int $offsetX = null,
        ?int $offsetY = null,
        ?int $opacity = null,
        ?int $width = null,
        ?int $height = null,
        ?int $customX = null,
        ?int $customY = null
    ): static {
        if ($path !== null) {
            $this->watermarkImage = $path;
        }

        if ($position !== null) {
            $this->watermarkPosition = is_string($position)
                ? WatermarkPosition::tryFrom($position) ?? WatermarkPosition::BottomRight
                : $position;
        }

        if ($offsetX !== null) {
            $this->watermarkOffsetX = $offsetX;
        }

        if ($offsetY !== null) {
            $this->watermarkOffsetY = $offsetY;
        }

        if ($opacity !== null) {
            $this->watermarkOpacity = max(0, min(100, $opacity));
        }

        if ($width !== null) {
            $this->watermarkWidth = $width;
        }

        if ($height !== null) {
            $this->watermarkHeight = $height;
        }

        if ($customX !== null) {
            $this->customPositionX = $customX;
        }

        if ($customY !== null) {
            $this->customPositionY = $customY;
        }

        return $this;
    }

    public function watermarkText(
        ?string $text = null,
        WatermarkPosition|string|null $position = null,
        ?int $offsetX = null,
        ?int $offsetY = null,
        ?int $size = null,
        ?string $color = null,
        ?int $customX = null,
        ?int $customY = null
    ): static {
        if ($text !== null) {
            $this->watermarkText = $text;
        }

        if ($position !== null) {
            $this->watermarkTextPosition = is_string($position)
                ? WatermarkPosition::tryFrom($position) ?? WatermarkPosition::BottomRight
                : $position;
        }

        if ($offsetX !== null) {
            $this->watermarkTextOffsetX = $offsetX;
        }

        if ($offsetY !== null) {
            $this->watermarkTextOffsetY = $offsetY;
        }

        if ($size !== null) {
            $this->watermarkTextSize = $size;
        }

        if ($color !== null) {
            $this->watermarkTextColor = $color;
        }

        if ($customX !== null) {
            $this->customTextPositionX = $customX;
        }

        if ($customY !== null) {
            $this->customTextPositionY = $customY;
        }

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

    public function processStoredImage(string $relativePath): void
    {
        $this->processImage($relativePath);
    }

    protected function processImage(string $relativePath): void
    {
        $disk = $this->getDisk();
        $storage = Storage::disk($disk);

        if (! $storage->exists($relativePath)) {
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
        $config = $this->createProcessingConfig();
        $job = new ProcessImage($relativePath, $disk, $config->toArray());

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
    }

    protected function processImageSync(string $relativePath, string $disk): void
    {
        $storage = Storage::disk($disk);
        $fullPath = $storage->path($relativePath);

        $config = $this->createProcessingConfig();
        $processor = new ImageProcessor($config);
        $processor->process($fullPath);
    }

    protected function createProcessingConfig(): ImageProcessingConfig
    {
        return new ImageProcessingConfig(
            quality: $this->getQuality(),
            qualityWebp: $this->getQualityWebp(),
            qualityAvif: $this->getQualityAvif(),
            generateWebp: $this->getGenerateWebp(),
            generateAvif: $this->getGenerateAvif(),
            stripMetadata: $this->getStripMetadata(),
            maxWidth: $this->getMaxWidth(),
            maxHeight: $this->getMaxHeight(),
            pngIndexed: $this->getPngIndexed(),
            pngColors: $this->getPngColors(),
            logging: $this->isLoggingEnabled(),
            watermark: $this->getWatermarkOptions(),
            watermarkText: $this->getWatermarkTextOptions(),
        );
    }

    protected function isQueueEnabled(): bool
    {
        return $this->queueEnabled ?? (bool) config('moonshine-intervention-image.queue.enabled', false);
    }

    protected function isLoggingEnabled(): bool
    {
        return $this->logging ?? (bool) config('moonshine-intervention-image.default.logging', false);
    }

    protected function getQuality(): int
    {
        return $this->quality ?? (int) config('moonshine-intervention-image.default.quality', 85);
    }

    protected function getQualityWebp(): int
    {
        return $this->qualityWebp ?? (int) config('moonshine-intervention-image.default.quality_webp', 80);
    }

    protected function getQualityAvif(): int
    {
        return $this->qualityAvif ?? (int) config('moonshine-intervention-image.default.quality_avif', 65);
    }

    protected function getGenerateWebp(): bool
    {
        return $this->generateWebp ?? (bool) config('moonshine-intervention-image.default.generate_webp', false);
    }

    protected function getGenerateAvif(): bool
    {
        return $this->generateAvif ?? (bool) config('moonshine-intervention-image.default.generate_avif', false);
    }

    protected function getStripMetadata(): bool
    {
        return $this->stripMetadata ?? (bool) config('moonshine-intervention-image.default.strip_metadata', false);
    }

    protected function getMaxWidth(): ?int
    {
        return $this->maxWidth ?? (config('moonshine-intervention-image.default.max_width') !== null
            ? (int) config('moonshine-intervention-image.default.max_width')
            : null);
    }

    protected function getMaxHeight(): ?int
    {
        return $this->maxHeight ?? (config('moonshine-intervention-image.default.max_height') !== null
            ? (int) config('moonshine-intervention-image.default.max_height')
            : null);
    }

    protected function getPngIndexed(): bool
    {
        return $this->pngIndexed ?? (bool) config('moonshine-intervention-image.png.indexed', false);
    }

    protected function getPngColors(): int
    {
        return $this->pngColors ?? (int) config('moonshine-intervention-image.png.colors', 256);
    }

    protected function getWatermarkOptions(): ?array
    {
        if ($this->watermarkDisabled) {
            return null;
        }

        $config = WatermarkConfig::fromFieldAndConfig(
            $this->watermarkImage,
            $this->watermarkPosition,
            $this->watermarkOffsetX,
            $this->watermarkOffsetY,
            $this->watermarkOpacity,
            $this->watermarkWidth,
            $this->watermarkHeight,
            $this->customPositionX,
            $this->customPositionY,
            config('moonshine-intervention-image.watermark', []),
        );

        return $config?->toArray();
    }

    protected function getWatermarkTextOptions(): ?array
    {
        if ($this->watermarkDisabled) {
            return null;
        }

        $config = TextWatermarkConfig::fromFieldAndConfig(
            $this->watermarkText,
            $this->watermarkTextFont,
            $this->watermarkTextSize,
            $this->watermarkTextColor,
            $this->watermarkTextPosition,
            $this->watermarkTextOffsetX,
            $this->watermarkTextOffsetY,
            $this->watermarkTextStrokeColor,
            $this->watermarkTextStrokeWidth,
            $this->watermarkTextAngle,
            $this->customTextPositionX,
            $this->customTextPositionY,
            config('moonshine-intervention-image.watermark_text', []),
        );

        return $config?->toArray();
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
        $storage = $this->getCore()->getStorage(disk: $disk);

        $basePath = PathHelper::getConvertedPath($fullPath, '');

        foreach (['webp', 'avif'] as $format) {
            $conversionPath = $basePath.$format;

            if ($storage->exists($conversionPath)) {
                $storage->delete($conversionPath);
            }
        }
    }

    public function getWebpPath(string $fileName): ?string
    {
        return $this->getConvertedPath($fileName, 'webp');
    }

    public function getAvifPath(string $fileName): ?string
    {
        return $this->getConvertedPath($fileName, 'avif');
    }

    private function getConvertedPath(string $fileName, string $format): ?string
    {
        $convertedPath = PathHelper::getConvertedPath($fileName, $format);
        $fullPath = $this->getPrependedDir($convertedPath);

        if ($this->getCore()->getStorage(disk: $this->getDisk())->exists($fullPath)) {
            return $this->getPath($fullPath);
        }

        return null;
    }
}
