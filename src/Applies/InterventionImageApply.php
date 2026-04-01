<?php

declare(strict_types=1);

namespace Povly\MoonshineInterventionImage\Applies;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use MoonShine\Contracts\UI\ApplyContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Exceptions\FileFieldException;
use Povly\MoonshineInterventionImage\Fields\InterventionImage;

final class InterventionImageApply implements ApplyContract
{
    public function apply(FieldContract $field): Closure
    {
        return function (mixed $item) use ($field): mixed {
            $requestValue = $field->getRequestValue();
            $remainingValues = $field->getRemainingValues();

            $logging = (bool) config('moonshine-intervention-image.default.logging', false);

            if ($logging) {
                Log::info('[InterventionImage] apply', [
                    'field' => $field->getColumn(),
                    'requestValueType' => get_debug_type($requestValue),
                    'requestValue' => $requestValue instanceof UploadedFile
                        ? ['path' => $requestValue->getPathname(), 'originalName' => $requestValue->getClientOriginalName(), 'isValid' => $requestValue->isValid(), 'size' => $requestValue->getSize()]
                        : $requestValue,
                    'remainingValues' => $remainingValues->toArray(),
                    'isMultiple' => $field->isMultiple(),
                    'requestNameDot' => $field->getRequestNameDot(),
                ]);
            }

            data_forget($item, $field->getHiddenRemainingValuesKey());

            $newValue = $field->isMultiple() ? $remainingValues : $remainingValues->first();

            if ($requestValue !== false) {
                if ($field->isMultiple()) {
                    $paths = [];

                    foreach ($requestValue as $file) {
                        if ($file instanceof UploadedFile && $file->isValid()) {
                            $paths[] = $this->store($field, $file);
                        }
                    }

                    $newValue = $newValue->merge($paths)
                        ->values()
                        ->unique()
                        ->toArray();
                } elseif ($requestValue instanceof UploadedFile && $requestValue->isValid()) {
                    $newValue = $this->store($field, $requestValue);
                    $field->setRemainingValues([]);
                }
            }

            if ($newValue instanceof Collection) {
                $newValue = $newValue->toArray();
            }

            $field->removeExcludedFiles(
                $field->getCustomName() !== null || $field->isKeepOriginalFileName()
                    ? $newValue
                    : null,
            );

            return data_set($item, $field->getColumn(), $newValue);
        };
    }

    private function store(InterventionImage $field, UploadedFile $file): string
    {
        $extension = $file->extension();

        if (! $field->isAllowedExtension($extension)) {
            throw FileFieldException::extensionNotAllowed($extension);
        }

        if ($field->isKeepOriginalFileName()) {
            $path = $file->storeAs(
                $field->getDir(),
                $file->getClientOriginalName(),
                $field->getOptions(),
            );
        } elseif ($field->getCustomName() !== null) {
            $path = $file->storeAs(
                $field->getDir(),
                \call_user_func($field->getCustomName(), $file, $field),
                $field->getOptions(),
            );
        } else {
            $path = $file->store($field->getDir(), $field->getOptions());

            if (! $path) {
                throw FileFieldException::failedSave();
            }
        }

        $field->processStoredImage($path);

        return $path;
    }
}
