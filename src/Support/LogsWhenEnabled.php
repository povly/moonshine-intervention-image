<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Support;

use Illuminate\Support\Facades\Log;

trait LogsWhenEnabled
{
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled()) {
            Log::info('[InterventionImage] '.$message, $context);
        }
    }

    private function logError(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled()) {
            Log::error('[InterventionImage] '.$message, $context);
        }
    }
}
