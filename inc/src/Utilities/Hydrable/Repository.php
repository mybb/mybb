<?php

declare(strict_types=1);

namespace MyBB\Utilities\Hydrable;

use InvalidArgumentException;
use MyBB\Utilities\Arrays;

class Repository
{
    private array $hydrables = [];

    public function __construct(
        private readonly ?StoreInterface $store = null,
    ) {}

    public function add(Hydrable $hydrable, array $path = []): Hydrable
    {
        $hydrable->store = $this->store;

        Arrays::setNested($this->hydrables, [$hydrable->key, ...$path], $hydrable);

        return $hydrable;
    }

    public function get(string $name, array $path = []): ?Hydrable
    {
        $value = Arrays::getNested($this->hydrables, [$name, ...$path]);

        if ($value instanceof Hydrable) {
            return $value;
        } else {
            throw new InvalidArgumentException();
        }
    }

    public function delete(string $name, array $path = []): void
    {
        $this->store->delete($name, $path);
    }

    public function clear(): void
    {
        if ($this->store) {
            foreach ($this->hydrables as $hydrable) {
                $hydrable->delete();
            }
        }
    }
}
