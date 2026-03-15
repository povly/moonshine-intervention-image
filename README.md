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

Publish the intervention/image config (optional):

```bash
php artisan vendor:publish --provider="Intervention\Image\Laravel\ServiceProvider"
```

## Requirements

- PHP 8.2+
- Laravel 11+ / 12+
- MoonShine 4.x
- intervention/image ^3.0
- intervention/image-laravel ^1.0
- GD or Imagick extension

## Usage

```php
use Povly\MoonshineInterventionImage\Fields\InterventionImage;

// Basic usage
InterventionImage::make('Image', 'image')

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
```

## Methods

| Method                                            | Description                              |
| ------------------------------------------------- | ---------------------------------------- |
| `generateWebp(bool $generate = true)`             | Generate WebP version of the image       |
| `generateAvif(bool $generate = true)`             | Generate AVIF version of the image       |
| `quality(int $quality)`                           | Set quality (1-100, default: 85)         |
| `stripMetadata(bool $strip = true)`               | Strip EXIF/IPTC metadata from images     |
| `maxDimensions(?int $width, ?int $height = null)` | Resize images while keeping aspect ratio |

## Multiple Images with AJAX Deletion (Layouts)

When using `multiple()` images inside Layouts (JSON fields), you need to handle AJAX deletion of converted files (WebP/AVIF). Create a base resource with the deletion method:

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
}
```

Then use it in your form with `removable()`:

```php
use Povly\MoonshineInterventionImage\Fields\InterventionImage;

InterventionImage::make('Images', 'gallery')
    ->dir('news/gallery')
    ->generateWebp()
    ->generateAvif()
    ->multiple()
    ->removable(attributes: self::getRemovableImageAttributes($page, 'gallery'));
```

Add the JavaScript handler to your Layout's `assets()` method:

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
