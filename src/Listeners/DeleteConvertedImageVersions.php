<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Povly\MoonshineInterventionImage\Support\PathHelper;
use YuriZoom\MoonShineMediaManager\Events\MediaManagerFileDeleted;

final class DeleteConvertedImageVersions
{
    protected array $imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    public function handle(MediaManagerFileDeleted $event): void
    {
        $extension = strtolower(pathinfo($event->path, PATHINFO_EXTENSION));

        if (! in_array($extension, $this->imageExtensions, true)) {
            Log::debug('[DeleteConvertedImageVersions] File is not an image, skipping', [
                'path' => $event->path,
            ]);

            return;
        }

        $storage = Storage::disk($event->disk);
        $deletedPaths = [];

        foreach (['webp', 'avif'] as $format) {
            $convertedPath = PathHelper::getConvertedPath($event->path, $format);

            if ($storage->exists($convertedPath)) {
                $storage->delete($convertedPath);
                $deletedPaths[] = $convertedPath;

                Log::debug('[DeleteConvertedImageVersions] Deleted converted version', [
                    'original' => $event->path,
                    'converted' => $convertedPath,
                    'format' => $format,
                ]);
            }
        }

        if (! empty($deletedPaths)) {
            Log::info('[DeleteConvertedImageVersions] Deleted converted image versions', [
                'original' => $event->path,
                'deleted' => $deletedPaths,
            ]);
        }
    }
}
