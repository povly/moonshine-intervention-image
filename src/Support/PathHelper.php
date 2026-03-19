<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Support;

final class PathHelper
{
    public static function getConvertedPath(string $originalPath, string $format): string
    {
        $info = pathinfo($originalPath);

        return $info['dirname'].'/'.$info['filename'].'.'.$format;
    }

    public static function isSupportedFormat(string $extension): bool
    {
        return in_array(strtolower($extension), ['jpeg', 'jpg', 'png', 'gif', 'webp'], true);
    }
}
