<?php

declare(strict_types=1);

namespace EmailValidation\Internal;

/**
 * Typed reads from decoded JSON. A field of the wrong type throws {@see \UnexpectedValueException}; the client turns
 * that into an `unexpected_response` error. Unknown fields are never looked at.
 *
 * @internal
 */
final class Decode
{
    /** @param array<mixed> $data */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw self::bad($key, 'a string');
        }

        return $value;
    }

    /** @param array<mixed> $data */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw self::bad($key, 'a string or null');
        }

        return $value;
    }

    /** @param array<mixed> $data */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value)) {
            throw self::bad($key, 'an integer');
        }

        return $value;
    }

    /** @param array<mixed> $data */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !is_int($value)) {
            throw self::bad($key, 'an integer or null');
        }

        return $value;
    }

    /** @param array<mixed> $data */
    public static function nullableBool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !is_bool($value)) {
            throw self::bad($key, 'a boolean or null');
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function object(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            throw self::bad($key, 'an object');
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    public static function listOfObjects(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw self::bad($key, 'a list');
        }
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw self::bad($key, 'a list of objects');
            }
        }
        /** @var list<array<mixed>> $value */
        return $value;
    }

    private static function bad(string $key, string $expected): \UnexpectedValueException
    {
        return new \UnexpectedValueException(sprintf('Field "%s" is not %s.', $key, $expected));
    }
}
