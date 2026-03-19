<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\ValueObjects;

use Povly\MoonshineInterventionImage\Enums\WatermarkPosition;

final readonly class WatermarkConfig
{
    public function __construct(
        public ?string $image = null,
        public ?string $position = null,
        public ?int $offsetX = null,
        public ?int $offsetY = null,
        public ?int $opacity = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?int $customX = null,
        public ?int $customY = null,
    ) {}

    public static function fromFieldAndConfig(
        ?string $fieldValue,
        ?WatermarkPosition $fieldPosition,
        ?int $fieldOffsetX,
        ?int $fieldOffsetY,
        ?int $fieldOpacity,
        ?int $fieldWidth,
        ?int $fieldHeight,
        ?int $fieldCustomX,
        ?int $fieldCustomY,
        array $globalConfig,
        string $configValueKey = 'image'
    ): ?self {
        if ($fieldValue !== null) {
            return new self(
                image: $fieldValue,
                position: $fieldPosition?->value ?? 'bottom-right',
                offsetX: $fieldOffsetX ?? 10,
                offsetY: $fieldOffsetY ?? 10,
                opacity: $fieldOpacity ?? 100,
                width: $fieldWidth,
                height: $fieldHeight,
                customX: $fieldCustomX,
                customY: $fieldCustomY,
            );
        }

        if (($globalConfig['enabled'] ?? false) && ($globalConfig[$configValueKey] ?? null)) {
            return new self(
                image: $globalConfig[$configValueKey],
                position: $fieldPosition?->value ?? ($globalConfig['position'] ?? 'bottom-right'),
                offsetX: $fieldOffsetX ?? ($globalConfig['offset_x'] ?? 10),
                offsetY: $fieldOffsetY ?? ($globalConfig['offset_y'] ?? 10),
                opacity: $fieldOpacity ?? ($globalConfig['opacity'] ?? 100),
                width: $fieldWidth ?? ($globalConfig['width'] ?? null),
                height: $fieldHeight ?? ($globalConfig['height'] ?? null),
                customX: $fieldCustomX ?? ($globalConfig['custom_position']['x'] ?? null),
                customY: $fieldCustomY ?? ($globalConfig['custom_position']['y'] ?? null),
            );
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'image' => $this->image,
            'position' => $this->position,
            'offset_x' => $this->offsetX,
            'offset_y' => $this->offsetY,
            'opacity' => $this->opacity,
            'width' => $this->width,
            'height' => $this->height,
            'custom_x' => $this->customX,
            'custom_y' => $this->customY,
        ];
    }
}
