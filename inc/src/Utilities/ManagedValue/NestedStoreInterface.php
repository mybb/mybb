<?php

declare(strict_types=1);

namespace MyBB\Utilities\ManagedValue;

/**
 * A store accepting array paths.
 */
interface NestedStoreInterface
{
    /**
     * Returns the configured array path.
     *
     * Useful for constructing global Managed Value identifiers.
     */
    public function getPath(): array;

    /**
     * Returns the value stored at the given path.
     *
     * @param non-empty-list<string> $path
     * @return mixed
     */
    public function get(array $path): mixed;

    /**
     * Stores the value at the given path.
     *
     * @param non-empty-list<string> $path
     */
    public function set(array $path, mixed $value): bool;

    /**
     * Removes the value at the given path from storage.
     *
     * @param non-empty-list<string> $path
     */
    public function delete(array $path): bool;

    /**
     * Locks the path key for modification (if supported).
     *
     * @param non-empty-list<string> $path
     */
    public function lock(array $path, bool $exclusive = true): bool;

    /**
     * Unlocks the path key for modification (if supported).
     *
     * @param non-empty-list<string> $path
     */
    public function unlock(array $path): bool;
}
