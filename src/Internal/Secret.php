<?php

declare(strict_types=1);

namespace EmailValidation\Internal;

/**
 * Holds a sensitive value (the API key, request headers and bodies) outside the object's own properties, so
 * `var_dump()`, `print_r()`, `var_export()`, `json_encode()` and `serialize()` never show it.
 *
 * @internal
 */
final class Secret
{
    /** @var \WeakMap<Secret, mixed>|null */
    private static ?\WeakMap $values = null;

    public function __construct(#[\SensitiveParameter] mixed $value)
    {
        self::map()[$this] = $value;
    }

    public function reveal(): mixed
    {
        return self::map()[$this] ?? null;
    }

    /** @return \WeakMap<Secret, mixed> */
    private static function map(): \WeakMap
    {
        return self::$values ??= new \WeakMap();
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }

    /** @return array<string, string> */
    public function __serialize(): array
    {
        throw new \LogicException('This object holds a secret and cannot be serialized.');
    }

    /** @param array<mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('This object holds a secret and cannot be unserialized.');
    }

    private function __clone()
    {
    }
}
