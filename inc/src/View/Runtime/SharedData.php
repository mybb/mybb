<?php

declare(strict_types=1);

namespace MyBB\View\Runtime;

use UnexpectedValueException;

/**
 * A container for data accessible within Resources.
 */
class SharedData
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Returns whether a value (including `null`) is stored under the given key.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Returns the value stored under the given key.
     */
    public function get(string $key): array|null|int|float|string|bool
    {
        return $this->data[$key] ?? throw new UnexpectedValueException('Key `' . $key . '` not found');
    }

    /**
     * Returns the value stored under the given key, or `null` if not found.
     */
    public function tryGet(string $key): array|null|int|float|string|bool
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Returns all stored values.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->data;
    }

    /**
     * Stores the given key-value pair.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Stores the given key-value pairs.
     *
     * @param array<string, mixed> $values
     */
    public function setMultiple(array $values): void
    {
        $this->data = array_merge($this->data, $values);
    }
}
