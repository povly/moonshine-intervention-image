<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Povly\MoonshineInterventionImage\Jobs\ProcessImage;
use Povly\MoonshineInterventionImage\Services\ImageProcessor;
use Povly\MoonshineInterventionImage\Support\PathHelper;
use Povly\MoonshineInterventionImage\ValueObjects\ImageProcessingConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'moonshine:image:optimize',
    description: 'Optimize images using intervention/image with WebP/AVIF conversion',
)]
final class OptimizeImages extends Command
{
    protected array $supportedExtensions = ['jpeg', 'jpg', 'png', 'gif', 'webp'];

    protected $signature = 'moonshine:image:optimize
        {paths?* : Specific file or directory paths to optimize (relative to disk root)}
        {--disk= : Storage disk to scan (default: from config)}
        {--preset= : Apply a preset from config}
        {--queue : Force queue processing}
        {--sync : Force synchronous processing}
        {--quality= : Override quality for original image (1-100)}
        {--quality-webp= : Override quality for WebP (1-100)}
        {--quality-avif= : Override quality for AVIF (1-100)}
        {--generate-webp : Generate WebP versions}
        {--no-webp : Disable WebP generation}
        {--generate-avif : Generate AVIF versions}
        {--no-avif : Disable AVIF generation}
        {--strip-metadata : Strip EXIF/IPTC metadata}
        {--max-width= : Maximum image width (keeps aspect ratio)}
        {--max-height= : Maximum image height (keeps aspect ratio)}
    ';

    protected $description = 'Optimize images using intervention/image with WebP/AVIF conversion';

    private int $processedCount = 0;

    private int $skippedCount = 0;

    private int $failedCount = 0;

    public function handle(): int
    {
        $disk = $this->option('disk') ?? config('moonshine-intervention-image.optimize.disk', 'public');
        $storage = Storage::disk($disk);

        if (! $storage->exists('/')) {
            $this->error("Storage disk [{$disk}] root directory does not exist.");

            return self::FAILURE;
        }

        $config = $this->buildProcessingConfig();
        $paths = $this->argument('paths');
        $useQueue = $this->resolveQueueMode();

        $this->info("Scanning disk [{$disk}] for images...");
        $this->info(sprintf(
            'Mode: %s | Quality: %d | WebP: %s | AVIF: %s',
            $useQueue ? 'queue' : 'sync',
            $config->quality,
            $config->generateWebp ? 'yes' : 'no',
            $config->generateAvif ? 'yes' : 'no'
        ));

        $files = empty($paths)
            ? $this->findFilesOnDisk($storage)
            : $this->findFilesInPaths($storage, $paths);

        if ($files->isEmpty()) {
            $this->warn('No supported images found.');

            return self::SUCCESS;
        }

        $total = $files->count();
        $this->info("Found {$total} image(s). Processing...");

        $this->processedCount = 0;
        $this->skippedCount = 0;
        $this->failedCount = 0;

        if ($useQueue) {
            $this->processViaQueue($files, $config, $disk);
        } else {
            $this->processSynchronously($files, $config, $disk, $storage);
        }

        $this->newLine(2);
        $this->info("Done! Processed: {$this->processedCount} | Skipped: {$this->skippedCount} | Failed: {$this->failedCount}");

        return self::SUCCESS;
    }

    private function buildProcessingConfig(): ImageProcessingConfig
    {
        $presetName = $this->option('preset');
        $preset = [];

        if ($presetName !== null) {
            $preset = config("moonshine-intervention-image.presets.{$presetName}", []);

            if (empty($preset)) {
                $this->warn("Preset [{$presetName}] not found. Using default config.");
            }
        }

        $defaultConfig = config('moonshine-intervention-image.default', []);
        $pngConfig = config('moonshine-intervention-image.png', []);
        $watermarkConfig = config('moonshine-intervention-image.watermark', []);
        $watermarkTextConfig = config('moonshine-intervention-image.watermark_text', []);

        $quality = $this->option('quality')
            ? (int) $this->option('quality')
            : ($preset['quality'] ?? $defaultConfig['quality'] ?? 85);

        $qualityWebp = $this->option('quality-webp')
            ? (int) $this->option('quality-webp')
            : ($preset['quality_webp'] ?? $defaultConfig['quality_webp'] ?? 80);

        $qualityAvif = $this->option('quality-avif')
            ? (int) $this->option('quality-avif')
            : ($preset['quality_avif'] ?? $defaultConfig['quality_avif'] ?? 65);

        $generateWebp = $this->resolveFlag(
            'generate-webp',
            'no-webp',
            $preset['generate_webp'] ?? $defaultConfig['generate_webp'] ?? false
        );

        $generateAvif = $this->resolveFlag(
            'generate-avif',
            'no-avif',
            $preset['generate_avif'] ?? $defaultConfig['generate_avif'] ?? false
        );

        $stripMetadata = $this->option('strip-metadata')
            ? (bool) $this->option('strip-metadata')
            : ($preset['strip_metadata'] ?? $defaultConfig['strip_metadata'] ?? false);

        $maxWidth = $this->option('max-width')
            ? (int) $this->option('max-width')
            : ($preset['max_width'] ?? $defaultConfig['max_width'] ?? null);

        $maxHeight = $this->option('max-height')
            ? (int) $this->option('max-height')
            : ($preset['max_height'] ?? $defaultConfig['max_height'] ?? null);

        return new ImageProcessingConfig(
            quality: max(1, min(100, $quality)),
            qualityWebp: max(1, min(100, $qualityWebp)),
            qualityAvif: max(1, min(100, $qualityAvif)),
            generateWebp: $generateWebp,
            generateAvif: $generateAvif,
            stripMetadata: $stripMetadata,
            maxWidth: $maxWidth,
            maxHeight: $maxHeight,
            pngIndexed: $preset['png_indexed'] ?? $pngConfig['indexed'] ?? false,
            pngColors: $preset['png_colors'] ?? $pngConfig['colors'] ?? 256,
            logging: $defaultConfig['logging'] ?? false,
            watermark: ($watermarkConfig['enabled'] ?? false) ? $watermarkConfig : null,
            watermarkText: ($watermarkTextConfig['enabled'] ?? false) ? $watermarkTextConfig : null,
        );
    }

    private function resolveQueueMode(): bool
    {
        if ($this->option('queue')) {
            return true;
        }

        if ($this->option('sync')) {
            return false;
        }

        return (bool) config('moonshine-intervention-image.queue.enabled', false);
    }

    private function resolveFlag(string $enableOption, string $disableOption, bool $default): bool
    {
        if ($this->option($enableOption)) {
            return true;
        }

        if ($this->option($disableOption)) {
            return false;
        }

        return $default;
    }

    private function findFilesOnDisk($storage): \Illuminate\Support\Collection
    {
        $rootPath = $storage->path('/');
        $excludePaths = config('moonshine-intervention-image.optimize.exclude_paths', []);

        $finder = Finder::create()
            ->files()
            ->in($rootPath)
            ->filter(function (\SplFileInfo $file) use ($excludePaths, $rootPath): bool {
                $extension = strtolower($file->getExtension());

                if (! PathHelper::isSupportedFormat($extension)) {
                    return false;
                }

                $relativePath = ltrim(str_replace($rootPath, '', $file->getPathname()), '/');

                foreach ($excludePaths as $excludePath) {
                    if (str_starts_with($relativePath, $excludePath)) {
                        return false;
                    }
                }

                return true;
            });

        return collect(iterator_to_array($finder, false))
            ->map(fn (\SplFileInfo $file) => $file->getPathname());
    }

    private function findFilesInPaths($storage, array $paths): \Illuminate\Support\Collection
    {
        $files = collect();

        foreach ($paths as $path) {
            $fullPath = $storage->path($path);

            if (! file_exists($fullPath)) {
                $this->warn("Path not found: {$path}");

                continue;
            }

            if (is_file($fullPath)) {
                $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

                if (PathHelper::isSupportedFormat($extension)) {
                    $files->push($fullPath);
                } else {
                    $this->warn("Unsupported format: {$path}");
                    $this->skippedCount++;
                }

                continue;
            }

            if (is_dir($fullPath)) {
                $finder = Finder::create()
                    ->files()
                    ->in($fullPath)
                    ->filter(fn (\SplFileInfo $file): bool => PathHelper::isSupportedFormat(
                        strtolower($file->getExtension())
                    ));

                foreach ($finder as $file) {
                    $files->push($file->getPathname());
                }
            }
        }

        return $files->unique();
    }

    private function processSynchronously(
        \Illuminate\Support\Collection $files,
        ImageProcessingConfig $config,
        string $disk,
        $storage
    ): void {
        $processor = new ImageProcessor($config);

        foreach ($files as $index => $fullPath) {
            $relativePath = ltrim(str_replace($storage->path('/'), '', $fullPath), '/');

            $this->output->write("\r  [" . ($index + 1) . '/' . $files->count() . "] Processing: {$relativePath}");

            try {
                $processor->process($fullPath);
                $this->processedCount++;
            } catch (\Exception $e) {
                $this->failedCount++;
                $this->newLine();
                $this->warn("  Failed: {$relativePath} - {$e->getMessage()}");
            }
        }

        $this->newLine();
    }

    private function processViaQueue(
        \Illuminate\Support\Collection $files,
        ImageProcessingConfig $config,
        string $disk
    ): void {
        $connection = config('moonshine-intervention-image.queue.connection');
        $queue = config('moonshine-intervention-image.queue.queue', 'images');
        $delay = config('moonshine-intervention-image.queue.delay');

        $storage = Storage::disk($disk);

        foreach ($files as $index => $fullPath) {
            $relativePath = ltrim(str_replace($storage->path('/'), '', $fullPath), '/');

            $this->output->write("\r  [" . ($index + 1) . '/' . $files->count() . "] Dispatching: {$relativePath}");

            $job = new ProcessImage($relativePath, $disk, $config->toArray());

            if ($connection) {
                $job->onConnection($connection);
            }

            $job->onQueue($queue);

            if ($delay !== null) {
                $job->delay($delay);
            }

            dispatch($job);
            $this->processedCount++;
        }

        $this->newLine();
        $this->info("  {$this->processedCount} job(s) dispatched to queue [{$queue}].");
    }
}
