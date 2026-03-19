<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Enums;

enum WatermarkPosition: string
{
    case TopLeft = 'top-left';
    case Top = 'top';
    case TopRight = 'top-right';
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';
    case BottomLeft = 'bottom-left';
    case Bottom = 'bottom';
    case BottomRight = 'bottom-right';
    case Custom = 'custom';
}
