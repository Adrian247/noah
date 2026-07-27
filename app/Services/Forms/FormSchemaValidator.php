<?php

namespace App\Services\Forms;

use Illuminate\Validation\ValidationException;

class FormSchemaValidator
{
    public const MAX_PHOTO_COUNT = 10;

    /**
     * @param  array<string, mixed>  $schema
     */
    public function validate(array $schema): void
    {
        $errors = [];

        foreach ($schema['sections'] ?? [] as $si => $section) {
            foreach ($section['fields'] ?? [] as $fi => $field) {
                $type = $field['type'] ?? 'text';
                if ($type !== 'photo') {
                    continue;
                }

                $prefix = "schema.sections.{$si}.fields.{$fi}";
                $allowMultiple = (bool) ($field['allow_multiple'] ?? false);
                $maxImages = (int) ($field['max_images'] ?? 4);
                $captionEnabled = (bool) ($field['caption_enabled'] ?? false);
                $captionRequired = (bool) ($field['caption_required'] ?? false);

                if ($allowMultiple && $maxImages < 1) {
                    $errors["{$prefix}.max_images"] = 'El máximo de imágenes debe ser al menos 1.';
                }

                if ($maxImages > self::MAX_PHOTO_COUNT) {
                    $errors["{$prefix}.max_images"] = 'El máximo de imágenes no puede superar '.self::MAX_PHOTO_COUNT.'.';
                }

                if ($captionRequired && ! $captionEnabled) {
                    $errors["{$prefix}.caption_required"] = 'La descripción obligatoria requiere activar «Añadir descripción».';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
