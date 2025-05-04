<?php

declare(strict_types=1);

namespace MyBB\Utilities\ManagedValue;

use InvalidArgumentException;
use LogicException;
use MyBB\Stopwatch\Stopwatch;
use MyBB\Utilities\Arrays;

/**
 * A runtime registry for defined Managed Values.
 */
class Repository
{
    /**
     * A mapping of paths to Managed Values.
     */
    private array $nestedManagedValues = [];

    /**
     * @param ?string[] $path The Repository store path used in informal identifiers.
     */
    public function __construct(
        public ?NestedStoreInterface $store = null,
        public ?array $path = [],
        private readonly ?Stopwatch $stopwatch = null,
    ) {}

    /**
     * Instantiates and registers a Managed Value.
     *
     * @param string|non-empty-list<string> $path
     */
    public function create(string|array $path): ManagedValue
    {
        $path = match (true) {
            is_string($path) && $path !== '' => [$path],
            is_array($path) && array_is_list($path) => $path,
            default => throw new InvalidArgumentException(),
        };

        $managedValue = new ManagedValue(
            $this->store,
            $path,
            $this->stopwatch,
        );

        Arrays::setNested($this->nestedManagedValues, $path, $managedValue);

        return $managedValue;
    }

    /**
     * Returns a previously registered Managed Value.
     *
     * @pure
     */
    public function get(string|array $path): ManagedValue
    {
        $path = match (true) {
            is_string($path) && $path !== '' => [$path],
            is_array($path) && array_is_list($path) => $path,
            default => throw new InvalidArgumentException(),
        };

        return
            Arrays::getNested($this->nestedManagedValues, $path)
            ?? throw new LogicException('Unregistered ManagedValue `' . implode('/', $path) . '`')
        ;
    }

    /**
     * Deletes the in-memory and stored values in all registered Managed Values.
     */
    public function clear(): void
    {
        if ($this->store) {
            array_walk_recursive(
                $this->nestedManagedValues,
                fn (ManagedValue $managedValue) => $managedValue->clear(),
            );
        }
    }
}
