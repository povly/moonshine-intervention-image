<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Providers;

use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\AppliesRegisterContract;
use MoonShine\Laravel\Resources\ModelResource;
use Povly\MoonshineInterventionImage\Applies\InterventionImageApply;
use Povly\MoonshineInterventionImage\Fields\InterventionImage;

final class MoonshineInterventionImageServiceProvider extends ServiceProvider
{
    public function boot(AppliesRegisterContract $appliesRegister): void
    {
        $appliesRegister
            ->for(ModelResource::class)
            ->fields()
            ->add(InterventionImage::class, InterventionImageApply::class);
    }
}
