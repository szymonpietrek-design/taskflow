<?php

namespace App\Support\I18n;

use JsonException;

/** Discover installed language packs directly from lang/{locale}.json. */
class LanguageCatalog
{
    public function __construct(private ?string $directory = null) {}

    /** @return array<int, array{value: string, label: string}> */
    public function options(): array
    {
        $options = [];
        foreach (glob($this->directory().'/*.json') ?: [] as $path) {
            $code = basename($path, '.json');
            $pack = $this->read($code);
            if ($pack === null) {
                continue;
            }
            $name = $pack['_nativeName'] ?? $code;
            $options[] = ['value' => $code, 'label' => $name];
        }

        usort($options, static fn (array $a, array $b): int => strcmp($a['value'], $b['value']));

        return $options;
    }

    public function has(string $locale): bool
    {
        return $this->read($locale) !== null;
    }

    public function normalize(mixed $locale): string
    {
        return is_string($locale) && $this->has($locale) ? $locale : 'en';
    }

    /** @return array<string, string>|null */
    public function read(string $locale): ?array
    {
        if (! preg_match('/\A[a-z]{2,3}(?:-[A-Za-z]{2,4})?\z/D', $locale)) {
            return null;
        }

        $file = $this->directory().'/'.$locale.'.json';
        if (! is_file($file) || is_link($file) || filesize($file) > 1024 * 1024) {
            return null;
        }

        try {
            $content = file_get_contents($file);
            if ($content === false) {
                return null;
            }
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data) || array_is_list($data)) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (! is_string($key) || ! is_string($value) || strlen($key) > 255 || strlen($value) > 10000) {
                return null;
            }
        }

        if (isset($data['_nativeName']) && (trim($data['_nativeName']) === '' || strlen($data['_nativeName']) > 240)) {
            return null;
        }

        return $data;
    }

    private function directory(): string
    {
        return $this->directory ?? lang_path();
    }
}
