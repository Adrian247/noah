<?php

namespace App\Services\Forms;

class PhotoResponseNormalizer
{
    /**
     * @return list<array{path: string, caption?: string}>
     */
    public static function toItems(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            return [['path' => $value]];
        }

        if (is_array($value) && isset($value['path'])) {
            $item = ['path' => (string) $value['path']];
            if (isset($value['caption']) && $value['caption'] !== '') {
                $item['caption'] = (string) $value['caption'];
            }

            return [$item];
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $entry) {
                if (is_string($entry) && $entry !== '') {
                    $items[] = ['path' => $entry];
                } elseif (is_array($entry) && ! empty($entry['path'])) {
                    $item = ['path' => (string) $entry['path']];
                    if (isset($entry['caption']) && $entry['caption'] !== '') {
                        $item['caption'] = (string) $entry['caption'];
                    }
                    $items[] = $item;
                }
            }

            return $items;
        }

        return [];
    }

    public static function isEmpty(mixed $value): bool
    {
        return self::toItems($value) === [];
    }
}
