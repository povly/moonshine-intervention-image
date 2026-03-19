<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\ValueObjects;

final readonly class ImageProcessingConfig
{
    public function __construct(
        public int $quality = 85,
        public int $qualityWebp = 80,
        public int $qualityAvif = 65,
        public bool $generateWebp = false,
        public bool $generateAvif = false,
        public bool $stripMetadata = false,
        public ?int $maxWidth = null,
        public ?int $maxHeight = null,
        public bool $pngIndexed = false,
        public int $pngColors = 256,
        public bool $logging = false,
        public ?array $watermark = null,
        public ?array $watermarkText = null,
    ) {}

    public static function fromArray(array $options): self
    {
        return new self(
            quality: $options['quality'] ?? 85,
            qualityWebp: $options['quality_webp'] ?? 80,
            qualityAvif: $options['quality_avif'] ?? 65,
            generateWebp: $options['generate_webp'] ?? false,
            generateAvif: $options['generate_avif'] ?? false,
            stripMetadata: $options['strip_metadata'] ?? false,
            maxWidth: $options['max_width'] ?? null,
            maxHeight: $options['max_height'] ?? null,
            pngIndexed: $options['png_indexed'] ?? false,
            pngColors: $options['png_colors'] ?? 256,
            logging: $options['logging'] ?? false,
            watermark: $options['watermark'] ?? null,
            watermarkText: $options['watermark_text'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
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
            'logging' => $this->logging,
            'watermark' => $this->watermark,
            'watermark_text' => $this->watermarkText,
        ];
    }
}
