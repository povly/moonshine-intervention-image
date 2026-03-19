<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Povly\MoonshineInterventionImage\Services\ImageProcessor;
use Povly\MoonshineInterventionImage\Support\LogsWhenEnabled;
use Povly\MoonshineInterventionImage\Support\PathHelper;
use Povly\MoonshineInterventionImage\ValueObjects\ImageProcessingConfig;

final class ProcessImage implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use LogsWhenEnabled;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $maxExceptions = 3;

    protected bool $logging;

    public function __construct(
        protected string $relativePath,
        protected string $disk = 'public',
        protected array $options = []
    ) {
        $this->tries = config('moonshine-intervention-image.queue.tries', 3);
        $this->timeout = config('moonshine-intervention-image.queue.timeout', 120);
        $this->logging = $options['logging'] ?? false;
    }

    public function handle(): void
    {
        $storage = Storage::disk($this->disk);

        if (! $storage->exists($this->relativePath)) {
            $this->logError('File not exists in storage', ['path' => $this->relativePath]);

            return;
        }

        $extension = strtolower(pathinfo($this->relativePath, PATHINFO_EXTENSION));

        if (! PathHelper::isSupportedFormat($extension)) {
            $this->logInfo('Skipping unsupported format', ['path' => $this->relativePath, 'format' => $extension]);

            return;
        }

        $fullPath = $storage->path($this->relativePath);

        $this->logInfo('Processing started', [
            'path' => $this->relativePath,
            'disk' => $this->disk,
            'attempt' => $this->attempts(),
        ]);

        $config = ImageProcessingConfig::fromArray($this->options);
        $processor = new ImageProcessor($config);
        $processor->process($fullPath);

        $this->logInfo('Processing completed', ['path' => $this->relativePath]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->logError('Job failed permanently', [
            'path' => $this->relativePath,
            'disk' => $this->disk,
            'error' => $exception->getMessage(),
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(1);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function displayName(): string
    {
        return "ProcessImage: {$this->relativePath}";
    }

    public function tags(): array
    {
        return [
            'moonshine-intervention-image',
            'disk:'.$this->disk,
            'path:'.$this->relativePath,
        ];
    }

    private function isLoggingEnabled(): bool
    {
        return $this->logging;
    }
}
