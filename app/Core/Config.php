<?php

declare(strict_types=1);

namespace Sofiago\Core;

final class Config
{
    /** @param array<string, mixed> $items */
    public function __construct(private array $items)
    {
    }

    /**
     * Dot-notation getter, e.g. get('db.host') or get('session.secure', true).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
