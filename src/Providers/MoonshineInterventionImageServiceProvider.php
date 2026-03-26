<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\AppliesRegisterContract;
use MoonShine\Laravel\Resources\ModelResource;
use Povly\MoonshineInterventionImage\Applies\InterventionImageApply;
use Povly\MoonshineInterventionImage\Fields\InterventionImage;
use Povly\MoonshineInterventionImage\Listeners\DeleteConvertedImageVersions;
use Povly\MoonshineInterventionImage\Listeners\ProcessUploadedImage;

final class MoonshineInterventionImageServiceProvider extends ServiceProvider
{
    public function boot(AppliesRegisterContract $appliesRegister): void
    {
        $this->publishes([
            __DIR__.'/../../config/moonshine-intervention-image.php' => config_path('moonshine-intervention-image.php'),
        ], 'moonshine-intervention-image-config');

        $this->mergeConfigFrom(
            __DIR__.'/../../config/moonshine-intervention-image.php',
            'moonshine-intervention-image'
        );

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'moonshine-intervention-image');

        $appliesRegister
            ->for(ModelResource::class)
            ->fields()
            ->add(InterventionImage::class, InterventionImageApply::class);

        $uploadedEventClass = 'YuriZoom\\MoonShineMediaManager\\Events\\MediaManagerFileUploaded';
        $deletedEventClass = 'YuriZoom\\MoonShineMediaManager\\Events\\MediaManagerFileDeleted';

        if (class_exists($uploadedEventClass)) {
            Event::listen($uploadedEventClass, ProcessUploadedImage::class);
        }

        if (class_exists($deletedEventClass)) {
            Event::listen($deletedEventClass, DeleteConvertedImageVersions::class);
        }
    }
}
