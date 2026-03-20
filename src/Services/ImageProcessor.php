<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Services;

use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Povly\MoonshineInterventionImage\Support\LogsWhenEnabled;
use Povly\MoonshineInterventionImage\Support\PathHelper;
use Povly\MoonshineInterventionImage\Support\PositionCalculator;
use Povly\MoonshineInterventionImage\ValueObjects\ImageProcessingConfig;
use RuntimeException;

final class ImageProcessor
{
    use LogsWhenEnabled;

    public function __construct(
        private ImageProcessingConfig $config
    ) {}

    public function process(string $fullPath): void
    {
        if (! PathHelper::isSupportedFormat(pathinfo($fullPath, PATHINFO_EXTENSION))) {
            return;
        }

        $this->optimizeImage($fullPath);

        if ($this->config->generateWebp) {
            $this->generateWebpVersion($fullPath);
        }

        if ($this->config->generateAvif) {
            $this->generateAvifVersion($fullPath);
        }
    }

    public function optimizeImage(string $fullPath): void
    {
        $sizeBefore = file_exists($fullPath) ? filesize($fullPath) : 0;

        try {
            $image = Image::read($fullPath);

            if ($this->config->maxWidth !== null || $this->config->maxHeight !== null) {
                $image = $image->scaleDown(
                    width: $this->config->maxWidth,
                    height: $this->config->maxHeight
                );
            }

            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

            if ($extension === 'png' && $this->config->pngIndexed) {
                $image->reduceColors($this->config->pngColors);
            }

            $this->applyWatermarks($image, $fullPath);

            $encoded = match ($extension) {
                'jpg', 'jpeg' => $image->toJpeg(
                    quality: $this->config->quality,
                    progressive: true,
                    strip: $this->config->stripMetadata,
                ),
                'png' => $image->toPng(
                    interlaced: true,
                    indexed: $this->config->pngIndexed,
                ),
                'gif' => $image->toGif(),
                'webp' => $image->toWebp(quality: $this->config->quality),
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

            throw new RuntimeException("Image optimization failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function generateWebpVersion(string $fullPath, ?int $quality = null): void
    {
        $webpPath = PathHelper::getConvertedPath($fullPath, 'webp');
        $quality ??= $this->config->qualityWebp;

        try {
            $image = Image::read($fullPath);
            $encoded = $image->toWebp(quality: $quality);
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

            throw new RuntimeException("WebP conversion failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function generateAvifVersion(string $fullPath, ?int $quality = null): void
    {
        $avifPath = PathHelper::getConvertedPath($fullPath, 'avif');
        $quality ??= $this->config->qualityAvif;

        try {
            $image = Image::read($fullPath);
            $encoded = $image->toAvif(quality: $quality);
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

            throw new RuntimeException("AVIF conversion failed: {$e->getMessage()}", 0, $e);
        }
    }

    private function applyWatermarks(ImageInterface $image, string $basePath): void
    {
        if ($this->config->watermark !== null && ! empty($this->config->watermark['image']) && file_exists($this->config->watermark['image'])) {
            $this->applyImageWatermark($image, $this->config->watermark);
        }

        if ($this->config->watermarkText !== null && ! empty($this->config->watermarkText['text'])) {
            $this->applyTextWatermark($image, $basePath, $this->config->watermarkText);
        }
    }

    private function applyImageWatermark(ImageInterface $image, array $options): void
    {
        try {
            $position = $options['position'] ?? 'bottom-right';
            $offsetX = $options['offset_x'] ?? 10;
            $offsetY = $options['offset_y'] ?? 10;
            $opacity = $options['opacity'] ?? 100;
            $width = $options['width'] ?? null;
            $height = $options['height'] ?? null;

            $watermark = Image::read($options['image']);

            if ($width !== null || $height !== null) {
                $watermark = $watermark->resize($width, $height);
            }

            if ($position === 'custom' && isset($options['custom_x'], $options['custom_y'])) {
                $image->place($watermark, 'top-left', $options['custom_x'], $options['custom_y'], $opacity);
            } else {
                $image->place($watermark, $position, $offsetX, $offsetY, $opacity);
            }

            $this->logInfo('Image watermark applied', [
                'watermark' => $options['image'],
                'position' => $position,
                'opacity' => $opacity,
            ]);
        } catch (\Exception $e) {
            $this->logError('Image watermark error', [
                'watermark' => $options['image'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function applyTextWatermark(ImageInterface $image, string $basePath, array $options): void
    {
        try {
            $imageWidth = $image->width();
            $imageHeight = $image->height();
            $fontSize = $options['size'] ?? 24;
            $position = $options['position'] ?? 'bottom-right';

            if ($position === 'custom' && isset($options['custom_x'], $options['custom_y'])) {
                $x = $options['custom_x'];
                $y = $options['custom_y'];
                $align = 'left';
                $valign = 'top';
            } else {
                [$x, $y, $align, $valign] = PositionCalculator::calculateTextPosition(
                    $imageWidth,
                    $imageHeight,
                    $fontSize,
                    $position,
                    $options['offset_x'] ?? 10,
                    $options['offset_y'] ?? 10
                );
            }

            $image->text($options['text'], $x, $y, function ($font) use ($basePath, $options, $fontSize, $align, $valign) {
                $fontPath = $options['font'] ?? null;

                if ($fontPath !== null) {
                    if (file_exists($fontPath)) {
                        $font->filename($fontPath);
                    } else {
                        $relativeFontPath = dirname($basePath).'/'.$fontPath;
                        if (file_exists($relativeFontPath)) {
                            $font->filename($relativeFontPath);
                        }
                    }
                }

                $font->size($fontSize);
                $font->color($options['color'] ?? 'ffffff');
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
                'position' => $position,
            ]);
        } catch (\Exception $e) {
            $this->logError('Text watermark error', [
                'text' => $options['text'] ?? '',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isLoggingEnabled(): bool
    {
        return $this->config->logging;
    }
}
