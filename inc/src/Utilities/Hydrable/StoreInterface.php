<?php

declare(strict_types=1);

namespace MyBB\Utilities\Hydrable;

interface StoreInterface
{
    public function get(string $key, array $path = []): mixed;
    public function set(string $key, array $path, array $value): bool;
    public function delete(string $key, array $path = []): void;
    public function lock(string $key, bool $exclusive = true): bool;
    public function unlock(string $key): bool;
}
