<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Support;

final class PositionCalculator
{
    public static function calculateTextPosition(
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
}
