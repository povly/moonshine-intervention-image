# MoonShine Intervention Image Field

Image optimization and WebP/AVIF conversion field for MoonShine using [intervention/image](https://image.intervention.io/).

## Installation

```bash
composer require povly/moonshine-intervention-image
```

The package requires `intervention/image` and `intervention/image-laravel`. If not already installed:

```bash
composer require intervention/image intervention/image-laravel
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=moonshine-intervention-image-config
```

## Requirements

- PHP 8.2+
- Laravel 11+ / 12+
- MoonShine 4.x
- intervention/image ^3.0
- intervention/image-laravel ^1.0
- GD or Imagick extension

## Configuration

Publish the config file to customize default settings:

```php
// config/moonshine-intervention-image.php
return [
    'default' => [
        'quality' => env('MOONSHINE_INTERVENTION_IMAGE_QUALITY', 85),
        'quality_webp' => env('MOONSHINE_INTERVENTION_IMAGE_QUALITY_WEBP', 80),
        'quality_avif' => env('MOONSHINE_INTERVENTION_IMAGE_QUALITY_AVIF', 65),
        'generate_webp' => env('MOONSHINE_INTERVENTION_IMAGE_WEBP', false),
        'generate_avif' => env('MOONSHINE_INTERVENTION_IMAGE_AVIF', false),
        'strip_metadata' => env('MOONSHINE_INTERVENTION_IMAGE_STRIP_METADATA', false),
        'max_width' => env('MOONSHINE_INTERVENTION_IMAGE_MAX_WIDTH'),
        'max_height' => env('MOONSHINE_INTERVENTION_IMAGE_MAX_HEIGHT'),
        'logging' => env('MOONSHINE_INTERVENTION_IMAGE_LOGGING', false),
    ],

    'png' => [
        'indexed' => env('MOONSHINE_INTERVENTION_IMAGE_PNG_INDEXED', true),
        'colors' => env('MOONSHINE_INTERVENTION_IMAGE_PNG_COLORS', 256),
    ],

    'queue' => [
        'enabled' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_ENABLED', false),
        'connection' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_CONNECTION'),
        'queue' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_NAME', 'images'),
        'delay' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_DELAY'),
        'tries' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_TRIES', 3),
        'timeout' => env('MOONSHINE_INTERVENTION_IMAGE_QUEUE_TIMEOUT', 120),
    ],

    'presets' => [
        'banner' => [
            'quality' => 85,
            'quality_webp' => 80,
            'quality_avif' => 65,
            'generate_webp' => true,
            'generate_avif' => false,
            'max_width' => 1920,
            'max_height' => 1080,
            'png_indexed' => true,
        ],
        'thumbnail' => [
            'quality' => 80,
            'quality_webp' => 75,
            'quality_avif' => 60,
            'generate_webp' => true,
            'generate_avif' => false,
            'max_width' => 400,
            'max_height' => 400,
            'png_indexed' => true,
        ],
        'gallery' => [
            'quality' => 85,
            'quality_webp' => 80,
            'quality_avif' => 65,
            'generate_webp' => true,
            'generate_avif' => true,
            'max_width' => 1920,
            'max_height' => 1080,
            'png_indexed' => true,
        ],
    ],
];
```

## Basic Usage

```php
use Povly\MoonshineInterventionImage\Fields\InterventionImage;

// Basic usage
InterventionImage::make('Image', 'image')

// Using preset from config
InterventionImage::make('Image', 'image')
    ->preset('banner')

// With WebP generation
InterventionImage::make('Image', 'image')
    ->generateWebp()

// With AVIF generation
InterventionImage::make('Image', 'image')
    ->generateAvif()

// With both formats
InterventionImage::make('Image', 'image')
    ->generateWebp()
    ->generateAvif()

// With quality control (1-100)
InterventionImage::make('Image', 'image')
    ->generateWebp()
    ->generateAvif()
    ->quality(85)           // Original image
    ->qualityWebp(80)       // WebP version
    ->qualityAvif(65)       // AVIF version

// PNG optimization with indexed colors (reduces file size significantly)
InterventionImage::make('Image', 'image')
    ->pngIndexed()                    // Uses default 256 colors
    ->pngIndexed(true, 128)           // Custom color count (2-256)

// Strip metadata (EXIF, IPTC, etc.)
InterventionImage::make('Image', 'image')
    ->stripMetadata()

// Resize images (keeps aspect ratio)
InterventionImage::make('Image', 'image')
    ->maxDimensions(1920, 1080)

// Multiple images
InterventionImage::make('Gallery', 'gallery')
    ->multiple()
    ->generateWebp()
    ->generateAvif()

// Enable logging (disabled by default)
InterventionImage::make('Image', 'image')
    ->generateWebp()
    ->logging()

// Queue processing (for large images)
InterventionImage::make('Image', 'image')
    ->generateWebp()
    ->queue()

// Queue with custom settings
InterventionImage::make('Image', 'image')
    ->generateWebp()
    ->queue(true, 'redis', 'images', 10) // enabled, connection, queue, delay(seconds)

// Custom quality for WebP and AVIF
InterventionImage::make('Image', 'image')
    ->generateWebp()
    ->generateAvif()
    ->quality(85)           // Original image quality
    ->qualityWebp(80)       // WebP quality
    ->qualityAvif(65)       // AVIF quality
```

## Queue Processing

Process images in the background using Laravel queues. Useful for handling large images or multiple uploads without blocking the user request.

### Enable via Config

```php
// config/moonshine-intervention-image.php
'queue' => [
    'enabled' => true,
    'connection' => null,     // null = default connection
    'queue' => 'images',
    'delay' => null,          // seconds or Carbon instance
    'tries' => 3,
    'timeout' => 120,
],
```

### Enable via Field Method

```php
// Enable queue with default config settings
InterventionImage::make('Image', 'image')
    ->generateWebp()
    ->queue()

// Override queue settings per field
InterventionImage::make('Image', 'image')
    ->generateWebp()
    ->queue(true, 'redis', 'high-priority', 5)

// Set only delay
InterventionImage::make('Image', 'image')
    ->generateWebp()
    ->queue()
    ->queueDelay(now()->addMinutes(5))
```

### Queue Job

The `ProcessImage` job handles:
- Image optimization
- WebP conversion
- AVIF conversion
- Automatic retries (3 attempts by default)
- 1 hour retry window
- Exponential backoff (10s, 30s, 60s)

```bash
# Start queue worker
php artisan queue:work --queue=images
```

## Methods

| Method                                                       | Description                                         |
| ------------------------------------------------------------ | --------------------------------------------------- |
| `preset(string $name)`                                       | Apply preset from config                            |
| `generateWebp(bool $generate = true)`                        | Generate WebP version of the image                  |
| `generateAvif(bool $generate = true)`                        | Generate AVIF version of the image                  |
| `quality(int $quality)`                                      | Set quality for original image (1-100, default: 85) |
| `qualityWebp(int $quality)`                                  | Set quality for WebP (1-100, default: 80)           |
| `qualityAvif(int $quality)`                                  | Set quality for AVIF (1-100, default: 65)           |
| `pngIndexed(bool $indexed = true, int $colors)`              | Optimize PNG with indexed colors (default: 256)     |
| `stripMetadata(bool $strip = true)`                          | Strip EXIF/IPTC metadata from images                |
| `maxDimensions(?int $width, ?int $height = null)`            | Resize images while keeping aspect ratio            |
| `queue(bool $enabled, ?string $connection, ?string $queue, $delay)` | Enable queue processing                      |
| `queueDelay(DateTimeInterface\|DateInterval\|int\|null $delay)` | Set queue delay                                |
| `logging(bool $enabled = true)`                              | Enable logging (disabled by default)                |

## PNG Optimization

PNG is a lossless format, so the `quality` parameter doesn't reduce file size. To optimize PNG files, use the `pngIndexed()` method which converts the image to use a palette of indexed colors (like GIF):

```php
InterventionImage::make('Image', 'image')
    ->pngIndexed()           // 256 colors (default)
    ->pngIndexed(true, 128)  // 128 colors - smaller file
    ->pngIndexed(true, 64)   // 64 colors - even smaller
```

This can significantly reduce PNG file size, especially for images with limited colors.

## AVIF Support

AVIF provides superior compression compared to WebP and JPEG. Use `qualityAvif()` to control the quality:

```php
InterventionImage::make('Image', 'image')
    ->generateAvif()
    ->qualityAvif(65)  // Lower values = smaller files, lower quality
```

Recommended AVIF quality values:
- **50-60**: Maximum compression, acceptable quality for thumbnails
- **60-70**: Good balance between size and quality (default: 65)
- **70-80**: High quality, still smaller than WebP/JPEG

**Note**: AVIF requires Imagick or GD with AVIF support compiled in.

---

## Complete Examples

### 1. Simple Usage (without layouts-field)

For single and multiple images without the [moonshine/layouts-field](https://github.com/moonshine-software/layouts-field) package.

#### Layout

Add the `removeImage` JavaScript function to your MoonShine layout:

```php
<?php

namespace App\MoonShine\Layouts;

use MoonShine\AssetManager\InlineJs;
use MoonShine\Laravel\Layouts\AppLayout;

final class MoonShineLayout extends AppLayout
{
    protected function assets(): array
    {
        return [
            ...parent::assets(),
            InlineJs::make(<<<'JS'
                window.removeImage = function(event, name) {
                    let button = event.currentTarget;
                    let imageIndex = button.closest('.dropzone-item')?.dataset.id;

                    if (imageIndex === undefined) {
                        return;
                    }

                    fetch(`${button.dataset.asyncUrl}&imageIndex=${imageIndex}&name=${name}`)
                        .then(() => button.closest('.x-removeable')?.remove());
                };
            JS),
        ];
    }
}
```

#### Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title',
        'image',
        'images',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
        ];
    }
}
```

#### Resource

```php
<?php

namespace App\MoonShine\Resources\Banner;

use App\Models\Banner;
use App\MoonShine\Resources\Banner\Pages\BannerIndexPage;
use App\MoonShine\Resources\Banner\Pages\BannerFormPage;
use App\MoonShine\Resources\Banner\Pages\BannerDetailPage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\AsyncMethod;


class BannerResource extends ModelResource
{
    protected string $model = Banner::class;

    protected string $title = 'Banners';

    protected function pages(): array
    {
        return [
            BannerIndexPage::class,
            BannerFormPage::class,
            BannerDetailPage::class,
        ];
    }

    #[AsyncMethod]
    public function removeImageData(CrudRequestContract $request): void
    {
        $item = $request->getResource()?->getItem();
        $imageIndex = $request->integer('imageIndex');
        $name = $request->string('name');

        if (is_null($item) || $imageIndex < 0) {
            return;
        }

        $images = $item->{$name} ?? [];

        if (isset($images[$imageIndex])) {
            $this->deleteImageWithConversions($images[$imageIndex]);
            Arr::forget($images, $imageIndex);
            $item->{$name} = array_values($images);
            $item->save();
        }
    }

    protected function deleteImageWithConversions(string $filePath, string $disk = 'public'): void
    {
        if (! Storage::disk($disk)->exists($filePath)) {
            return;
        }

        Storage::disk($disk)->delete($filePath);

        $info = pathinfo($filePath);
        $basePath = $info['dirname'].'/'.$info['filename'];

        foreach (['webp', 'avif'] as $format) {
            $conversionPath = $basePath.'.'.$format;

            if (Storage::disk($disk)->exists($conversionPath)) {
                Storage::disk($disk)->delete($conversionPath);
            }
        }
    }
}
```

#### FormPage

```php
<?php

namespace App\MoonShine\Resources\Banner\Pages;

use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Components\Layout\Box;
use Povly\MoonshineInterventionImage\Fields\InterventionImage;

class BannerFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Title', 'title')
                    ->required(),
                    
                // Single image
                InterventionImage::make('Image', 'image')
                    ->dir('banners')
                    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
                    ->generateWebp()
                    ->generateAvif()
                    ->quality(85)
                    ->qualityWebp(80)
                    ->qualityAvif(65)
                    ->pngIndexed()
                    ->maxDimensions(1920, 1080)
                    ->removable(),
                    
                // Multiple images with AJAX deletion
                InterventionImage::make('Gallery', 'images')
                    ->dir('banners/gallery')
                    ->multiple()
                    ->removable(attributes: $this->getRemovableImageAttributes('images'))
                    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
                    ->generateWebp()
                    ->generateAvif()
                    ->quality(85)
                    ->qualityWebp(80)
                    ->qualityAvif(65)
                    ->pngIndexed()
                    ->maxDimensions(1920, 1080),
            ]),
        ];
    }

    public function getRemovableImageAttributes(string $name): array
    {
        return [
            'data-async-url' => $this->getResource()
                ? $this->getRouter()->getEndpoints()->method(
                    'removeImageData',
                    params: ['resourceItem' => $this->getResource()->getItemID()]
                )
                : null,
            '@click.prevent' => "removeImage(\$event, '{$name}')",
        ];
    }
}
```

---

### 2. Usage with layouts-field

When using [moonshine/layouts-field](https://github.com/moonshine-software/layouts-field) package for flexible content blocks.

#### Layout

Add the `removeImage` and `removeLayoutImage` JavaScript functions to your MoonShine layout:

```php
<?php

namespace App\MoonShine\Layouts;

use MoonShine\AssetManager\InlineJs;
use MoonShine\Laravel\Layouts\AppLayout;

final class MoonShineLayout extends AppLayout
{
    protected function assets(): array
    {
        return [
            ...parent::assets(),
            InlineJs::make(<<<'JS'
                window.removeImage = function(event, name) {
                    let button = event.currentTarget;
                    let imageIndex = button.closest('.dropzone-item')?.dataset.id;

                    if (imageIndex === undefined) {
                        return;
                    }

                    fetch(`${button.dataset.asyncUrl}&imageIndex=${imageIndex}&name=${name}`)
                        .then(() => button.closest('.x-removeable')?.remove());
                };

                window.removeLayoutImage = function(event, name) {
                    let button = event.currentTarget;
                    let accordion = button.closest('.accordion');
                    let layoutIndex = parseInt(accordion.querySelector('[data-r-index]')?.dataset.rIndex ?? 0);
                    let imageIndex = button.closest('.dropzone-item')?.dataset.id ?? 0;
                    
                    fetch(`${button.dataset.asyncUrl}&imageIndex=${imageIndex}&layoutIndex=${layoutIndex}&name=${name}`)
                        .then(() => button.closest('.x-removeable')?.remove());
                };
            JS),
        ];
    }
}
```

#### Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Layouts\Casts\LayoutsCast;

class Banner extends Model
{
    protected $fillable = [
        'title',
        'image',
        'images',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'content' => LayoutsCast::class,
        ];
    }
}
```

#### Migration

```php
Schema::table('banners', function (Blueprint $table) {
    $table->json('content')->nullable();
});
```

#### Resource

```php
<?php

namespace App\MoonShine\Resources\Banner;

use App\Models\Banner;
use App\MoonShine\Resources\Banner\Pages\BannerIndexPage;
use App\MoonShine\Resources\Banner\Pages\BannerFormPage;
use App\MoonShine\Resources\Banner\Pages\BannerDetailPage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Layouts\Casts\LayoutItem;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\AsyncMethod;

class BannerResource extends ModelResource
{
    protected string $model = Banner::class;

    protected string $title = 'Banners';

    protected function pages(): array
    {
        return [
            BannerIndexPage::class,
            BannerFormPage::class,
            BannerDetailPage::class,
        ];
    }

    #[AsyncMethod]
    public function removeImageData(CrudRequestContract $request): void
    {
        $item = $request->getResource()?->getItem();
        $imageIndex = $request->integer('imageIndex');
        $name = $request->string('name');

        if (is_null($item) || $imageIndex < 0) {
            return;
        }

        $images = $item->{$name} ?? [];

        if (isset($images[$imageIndex])) {
            $this->deleteImageWithConversions($images[$imageIndex]);
            Arr::forget($images, $imageIndex);
            $item->{$name} = array_values($images);
            $item->save();
        }
    }

    #[AsyncMethod]
    public function removeLayoutImageData(CrudRequestContract $request): void
    {
        $item = $request->getResource()?->getItem();
        $imageIndex = $request->integer('imageIndex');
        $layoutIndex = $request->integer('layoutIndex');
        $name = $request->string('name');

        if (is_null($item) || $imageIndex < 0 || $layoutIndex < 0) {
            return;
        }

        $content = $item->content;

        if (! isset($content[$layoutIndex])) {
            return;
        }

        $values = $content[$layoutIndex] instanceof LayoutItem
            ? $content[$layoutIndex]->getValues()
            : $content[$layoutIndex]['values'];

        $images = data_get($values, $name);

        if (is_array($images) && isset($images[$imageIndex])) {
            $this->deleteImageWithConversions($images[$imageIndex]);
            Arr::forget($images, $imageIndex);
            data_set($values, $name, array_values($images));
        } elseif (is_string($images)) {
            $this->deleteImageWithConversions($images);
            data_set($values, $name, null);
        }

        if ($content[$layoutIndex] instanceof LayoutItem) {
            $content[$layoutIndex] = new LayoutItem(
                $content[$layoutIndex]->getName(),
                $content[$layoutIndex]->getKey(),
                $values
            );
        } else {
            $content[$layoutIndex]['values'] = $values;
        }

        $item->content = $content;
        $item->save();
    }

    protected function deleteImageWithConversions(string $filePath, string $disk = 'public'): void
    {
        if (! Storage::disk($disk)->exists($filePath)) {
            return;
        }

        Storage::disk($disk)->delete($filePath);

        $info = pathinfo($filePath);
        $basePath = $info['dirname'].'/'.$info['filename'];

        foreach (['webp', 'avif'] as $format) {
            $conversionPath = $basePath.'.'.$format;

            if (Storage::disk($disk)->exists($conversionPath)) {
                Storage::disk($disk)->delete($conversionPath);
            }
        }
    }
}
```

#### FormPage

```php
<?php

namespace App\MoonShine\Resources\Banner\Pages;

use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\Layouts\Fields\Layouts;
use Povly\MoonshineInterventionImage\Fields\InterventionImage;

class BannerFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Title', 'title')
                    ->required(),
                    
                // Single image (outside layouts)
                InterventionImage::make('Image', 'image')
                    ->dir('banners')
                    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
                    ->generateWebp()
                    ->generateAvif()
                    ->quality(85)
                    ->qualityWebp(80)
                    ->qualityAvif(65)
                    ->pngIndexed()
                    ->maxDimensions(1920, 1080)
                    ->removable(),
                    
                // Multiple images (outside layouts)
                InterventionImage::make('Gallery', 'images')
                    ->dir('banners/gallery')
                    ->multiple()
                    ->removable(attributes: $this->getRemovableImageAttributes('images'))
                    ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
                    ->generateWebp()
                    ->generateAvif()
                    ->quality(85)
                    ->qualityWebp(80)
                    ->qualityAvif(65)
                    ->pngIndexed()
                    ->maxDimensions(1920, 1080),
                    
                // Layouts field with images inside
                Layouts::make('Content', 'content')
                    ->addLayout('Image Block', 'image_block', [
                        Text::make('Title', 'title'),
                        InterventionImage::make('Image', 'image')
                            ->dir('banners/blocks')
                            ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
                            ->generateWebp()
                            ->generateAvif()
                            ->quality(85)
                            ->qualityWebp(80)
                            ->qualityAvif(65)
                            ->pngIndexed()
                            ->maxDimensions(1920, 1080)
                            ->removable(attributes: $this->getRemovableLayoutImageAttributes('image')),
                    ])
                    ->addLayout('Gallery Block', 'gallery', [
                        Text::make('Title', 'title'),
                        InterventionImage::make('Images', 'images')
                            ->dir('banners/gallery-blocks')
                            ->multiple()
                            ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
                            ->generateWebp()
                            ->generateAvif()
                            ->quality(85)
                            ->qualityWebp(80)
                            ->qualityAvif(65)
                            ->pngIndexed()
                            ->maxDimensions(1920, 1080)
                            ->removable(attributes: $this->getRemovableLayoutImageAttributes('images')),
                    ]),
            ]),
        ];
    }

    public function getRemovableImageAttributes(string $name): array
    {
        return [
            'data-async-url' => $this->getResource()
                ? $this->getRouter()->getEndpoints()->method(
                    'removeImageData',
                    params: ['resourceItem' => $this->getResource()->getItemID()]
                )
                : null,
            '@click.prevent' => "removeImage(\$event, '{$name}')",
        ];
    }

    public function getRemovableLayoutImageAttributes(string $name): array
    {
        return [
            'data-async-url' => $this->getResource()
                ? $this->getRouter()->getEndpoints()->method(
                    'removeLayoutImageData',
                    params: ['resourceItem' => $this->getResource()->getItemID()]
                )
                : null,
            '@click.prevent' => "removeLayoutImage(\$event, '{$name}')",
        ];
    }
}
```

---

## Supported Formats

- JPEG / JPG
- PNG
- GIF
- WebP
- AVIF

## Documentation

For actual files, configuration options, and the latest information about the required packages, please refer to their official documentation:

- [intervention/image](https://image.intervention.io/)
- [MoonShine](https://moonshine-laravel.com/)
- [moonshine/layouts-field](https://github.com/moonshine-software/layouts-field)

Package APIs and configurations may change over time, so always check the current documentation.

## License

MIT
