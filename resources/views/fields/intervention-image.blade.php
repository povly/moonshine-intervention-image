@props([
    'value' => '',
    'files' => [],
    'isRemovable' => false,
    'canDownload' => false,
    'removableAttributes' => null,
    'hiddenAttributes' => null,
    'dropzoneAttributes' => null,
])
@php
    $fieldId = $attributes->get('id', uniqid('field_'));
    $fieldName = $attributes->get('name');
@endphp
<div {{ $attributes->only('data-field-selector') }}>
    <x-moonshine::form.file
        :attributes="$attributes->except(['data-field-selector'])"
        :files="$files"
        :removable="$isRemovable"
        :removableAttributes="$removableAttributes"
        :hiddenAttributes="$hiddenAttributes"
        :dropzoneAttributes="$dropzoneAttributes"
        :imageable="true"
    />
</div>
