<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\ValueObjects;

use Povly\MoonshineInterventionImage\Enums\WatermarkPosition;

final readonly class TextWatermarkConfig
{
    public function __construct(
        public ?string $text = null,
        public ?string $font = null,
        public ?int $size = null,
        public ?string $color = null,
        public ?string $position = null,
        public ?int $offsetX = null,
        public ?int $offsetY = null,
        public ?string $strokeColor = null,
        public ?int $strokeWidth = null,
        public ?int $angle = null,
        public ?int $customX = null,
        public ?int $customY = null,
    ) {}

    public static function fromFieldAndConfig(
        ?string $fieldText,
        ?string $fieldFont,
        ?int $fieldSize,
        ?string $fieldColor,
        ?WatermarkPosition $fieldPosition,
        ?int $fieldOffsetX,
        ?int $fieldOffsetY,
        ?string $fieldStrokeColor,
        ?int $fieldStrokeWidth,
        ?int $fieldAngle,
        ?int $fieldCustomX,
        ?int $fieldCustomY,
        array $globalConfig
    ): ?self {
        if ($fieldText !== null) {
            return new self(
                text: $fieldText,
                font: $fieldFont,
                size: $fieldSize ?? 24,
                color: $fieldColor ?? 'ffffff',
                position: $fieldPosition?->value ?? 'bottom-right',
                offsetX: $fieldOffsetX ?? 10,
                offsetY: $fieldOffsetY ?? 10,
                strokeColor: $fieldStrokeColor,
                strokeWidth: $fieldStrokeWidth ?? 0,
                angle: $fieldAngle,
                customX: $fieldCustomX,
                customY: $fieldCustomY,
            );
        }

        if (($globalConfig['enabled'] ?? false) && ($globalConfig['text'] ?? null)) {
            return new self(
                text: $globalConfig['text'],
                font: $fieldFont ?? ($globalConfig['font'] ?? null),
                size: $fieldSize ?? ($globalConfig['size'] ?? 24),
                color: $fieldColor ?? ($globalConfig['color'] ?? 'ffffff'),
                position: $fieldPosition?->value ?? ($globalConfig['position'] ?? 'bottom-right'),
                offsetX: $fieldOffsetX ?? ($globalConfig['offset_x'] ?? 10),
                offsetY: $fieldOffsetY ?? ($globalConfig['offset_y'] ?? 10),
                strokeColor: $fieldStrokeColor ?? ($globalConfig['stroke_color'] ?? null),
                strokeWidth: $fieldStrokeWidth ?? ($globalConfig['stroke_width'] ?? 0),
                angle: $fieldAngle ?? ($globalConfig['angle'] ?? null),
                customX: $fieldCustomX ?? ($globalConfig['custom_position']['x'] ?? null),
                customY: $fieldCustomY ?? ($globalConfig['custom_position']['y'] ?? null),
            );
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'font' => $this->font,
            'size' => $this->size,
            'color' => $this->color,
            'position' => $this->position,
            'offset_x' => $this->offsetX,
            'offset_y' => $this->offsetY,
            'stroke_color' => $this->strokeColor,
            'stroke_width' => $this->strokeWidth,
            'angle' => $this->angle,
            'custom_x' => $this->customX,
            'custom_y' => $this->customY,
        ];
    }
}
