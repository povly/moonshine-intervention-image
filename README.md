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
        'quality' => env('MOONSHINE_IMAGE_QUALITY', 85),
        'generate_webp' => env('MOONSHINE_IMAGE_WEBP', false),
        'generate_avif' => env('MOONSHINE_IMAGE_AVIF', false),
        'strip_metadata' => env('MOONSHINE_IMAGE_STRIP_METADATA', false),
        'max_width' => env('MOONSHINE_IMAGE_MAX_WIDTH', null),
        'max_height' => env('MOONSHINE_IMAGE_MAX_HEIGHT', null),
        'logging' => env('MOONSHINE_IMAGE_LOGGING', false),
    ],

    'png' => [
        'indexed' => env('MOONSHINE_IMAGE_PNG_INDEXED', true),
        'colors' => env('MOONSHINE_IMAGE_PNG_COLORS', 256),
    ],

    'presets' => [
        'banner' => [
            'quality' => 85,
            'generate_webp' => true,
            'generate_avif' => false,
            'max_width' => 1920,
            'max_height' => 1080,
            'png_indexed' => true,
        ],
        'thumbnail' => [
            'quality' => 80,
            'generate_webp' => true,
            'generate_avif' => false,
            'max_width' => 400,
            'max_height' => 400,
            'png_indexed' => true,
        ],
        'gallery' => [
            'quality' => 85,
            'generate_webp' => true,
            'generate_avif' => true,
            'max_width' => 1920,
            'max_height' => 1080,
            'png_indexed' => true,
        ],
    ],
];
```

## Usage

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

// With quality control (1-100, default: 85)
InterventionImage::make('Image', 'image')
    ->generateWebp()
    ->quality(80)

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
```

## Methods

| Method                                            | Description                                         |
| ------------------------------------------------- | --------------------------------------------------- |
| `preset(string $name)`                            | Apply preset from config                            |
| `generateWebp(bool $generate = true)`             | Generate WebP version of the image                  |
| `generateAvif(bool $generate = true)`             | Generate AVIF version of the image                  |
| `quality(int $quality)`                           | Set quality (1-100, default: 85)                    |
| `pngIndexed(bool $indexed = true, int $colors)`   | Optimize PNG with indexed colors (default: 256)     |
| `stripMetadata(bool $strip = true)`               | Strip EXIF/IPTC metadata from images                |
| `maxDimensions(?int $width, ?int $height = null)` | Resize images while keeping aspect ratio            |
| `logging(bool $enabled = true)`                   | Enable logging (disabled by default)                |

## PNG Optimization

PNG is a lossless format, so the `quality` parameter doesn't reduce file size. To optimize PNG files, use the `pngIndexed()` method which converts the image to use a palette of indexed colors (like GIF):

```php
InterventionImage::make('Image', 'image')
    ->pngIndexed()           // 256 colors (default)
    ->pngIndexed(true, 128)  // 128 colors - smaller file
    ->pngIndexed(true, 64)   // 64 colors - even smaller
```

This can significantly reduce PNG file size, especially for images with limited colors.

## Multiple Images with AJAX Deletion

When using `multiple()` images with WebP/AVIF conversion, you need to handle AJAX deletion of converted files. The example below uses [moonshine/layouts-field](https://github.com/moonshine-software/layouts-field) package. If you use a different approach, implement the deletion logic according to your structure.

### Base Resource with deletion method:

```php
<?php

namespace App\MoonShine\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Layouts\Casts\LayoutItem;
use MoonShine\Support\Attributes\AsyncMethod;

class BaseResource extends ModelResource
{
    #[AsyncMethod]
    public function removeMainImagesData(CrudRequestContract $request)
    {
        $item = $request->getResource()?->getItem();
        $imageIndex = $request->integer('imageIndex');
        $accordionIndex = $request->integer('accordionIndex');
        $name = $request->string('name');

        if (is_null($item) || $imageIndex < 0) {
            return;
        }

        $values = $item->content[$accordionIndex]->getValues();

        $filePath = data_get($values, "{$name}.{$imageIndex}");

        if ($filePath) {
            $this->deleteImageWithConversions($filePath);
        }

        Arr::forget($values, "{$name}.{$imageIndex}");

        $images = data_get($values, $name);

        if (is_array($images)) {
            data_set($values, $name, array_values($images));
        }

        $item->content[$accordionIndex] = new LayoutItem(
            $item->content[$accordionIndex]->getName(),
            $item->content[$accordionIndex]->getKey(),
            $values
        );

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

### Simple Multiple Images (without layouts-field):

```php
// In your Resource:
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
```

### FormPage with helper method:

```php
use Povly\MoonshineInterventionImage\Fields\InterventionImage;

class NewsFormPage extends FormPage
{
    public static function getRemovableImageAttributes($page, string $name): array
    {
        return [
            'data-async-url' => $page->getResource()
                ? $page->getRouter()->getEndpoints()->method('removeMainImagesData',
                    params: ['resourceItem' => $page->getResource()->getItemID()])
                : null,
            '@click.prevent' => "removeMainImage(\$event, '{$name}')",
        ];
    }

    protected function fields(): iterable
    {
        return [
            // ...
            InterventionImage::make('Images', 'gallery')
                ->dir('news/gallery')
                ->generateWebp()
                ->generateAvif()
                ->multiple()
                ->removable(attributes: self::getRemovableImageAttributes($this, 'gallery')),
        ];
    }
}
```

### JavaScript handler in Layout's `assets()` method:

```php
use MoonShine\UI\Components\InlineJs;

// In your Layout class:
protected function assets(): array
{
    return [
        ...parent::assets(),
        InlineJs::make(<<<'JS'
            window.removeMainImage = function(event, name) {
                let button = event.currentTarget;
                let accordion = button.closest('.accordion');
                let accordionIndexValue = parseInt(accordion.querySelector('[data-r-index]').dataset.rIndex);

                fetch(`${button.dataset.asyncUrl}&imageIndex=${button.closest('.dropzone-item').dataset.id}&accordionIndex=${accordionIndexValue}&name=${name}`)
                    .then(() => button.closest('.x-removeable').remove());
            };
        JS),
    ];
}
```

> **Note:** This example uses `moonshine/layouts-field` package structure. If you use a different approach for multiple images, you need to adapt the deletion logic according to your implementation. The key point is to delete the original file and its WebP/AVIF conversions when an image is removed.

## Supported Formats

- JPEG / JPG
- PNG
- GIF
- WebP

## Documentation

For actual files, configuration options, and the latest information about the required packages, please refer to their official documentation:

- [intervention/image](https://image.intervention.io/)
- [MoonShine](https://moonshine-laravel.com/)

Package APIs and configurations may change over time, so always check the current documentation.

## License

MIT
