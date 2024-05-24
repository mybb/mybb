<?php

declare(strict_types=1);

namespace MyBB\Utilities\Hydrable;

use InvalidArgumentException;
use LogicException;
use MyBB\Stopwatch\Stopwatch;
use MyBB\Utilities\Arrays;
use ReflectionFunction;
use RuntimeException;

use function MyBB\app;

/**
 * Stores data with custom initialization, serialization, and caching strategies.
 *
 * @template T mixed
 */
class Hydrable
{
    final public const MODE_IMMEDIATE = 2;
    final public const MODE_DEFERRED = 4;
    final public const MODE_PASSIVE = 8;

    private const DATA_STAMP = 'stamp';
    private const DATA_VALUE = 'value';

    public ?StoreInterface $store;

    /**
     * @var array<string, array{
     *   build: ?callable(): T,
     *   validateStamp: ?callable,
     * }
     */
    private array $closures;

    /**
     * @var array<string, array{
     *   write: ?callable(T $data),
     *   read: ?callable():T,
     * }
     */
    private array $filters;

    private bool $supportsStamping;

    /**
     * @var T
     */
    private mixed $value;

    private mixed $stamp = null;

    private bool $initialized = false;
    private bool $pendingWrite = false;
    private bool $stampValidated = false;

    private ?Stopwatch $stopwatch;

    public function __construct(
        /**
         * @var T
         */
        private readonly mixed $default,

        public readonly ?string $key = null,
        public readonly array $path = [],

        ?callable $build = null,


        ?callable $validateStamp = null,

        ?callable $write = null,
        ?callable $read = null,

        /**
         * Strategy for initializing with the default value.
         *
         * @var self::MODE_*
         */
        public int $defaultMode = self::MODE_DEFERRED,

        /**
         * Strategy for initializing with the build callback.
         *
         * @var self::MODE_*
         */
        public int $buildMode = self::MODE_DEFERRED,

        /**
         * Strategy for writing data to the store.
         *
         * @var self::MODE_*
         */
        public int $writeMode = self::MODE_DEFERRED,

        /**
         * Strategy for initializing with stored value.
         *
         * @var self::MODE_*
         */
        public int $readMode = self::MODE_DEFERRED,

        /**
         * Strategy for validating stamps when loading from the store.
         *
         * @var self::MODE_*
         */
        public int $validateStampMode = self::MODE_IMMEDIATE,

        public bool $ignoreSet = false,
    ) {
        $this->stopwatch = app(Stopwatch::class);

        $this->closures = [
            'build' => $build,
            'validateStamp' => $validateStamp,
        ];

        $this->filters = [
            'write' => $write,
            'read' => $read,
        ];

        $this->supportsStamping = is_callable($build) && self::providesStamp($build);

        foreach (['default', 'read', 'build'] as $action) {
            if (${$action . 'Mode'} === self::MODE_IMMEDIATE) {
                $this->$action();
            }
        }
    }

    private static function providesStamp(callable $callable): bool
    {
        $reflection = new ReflectionFunction($callable);

        $parameters = $reflection->getParameters();

        foreach ($parameters as $parameter) {
            if ($parameter->isPassedByReference() && $parameter->getName() === 'stamp') {
                return true;
            }
        }

        return false;
    }

    public function __destruct()
    {
        $this->commit();
    }

    public function initialize(): void
    {
        if ($this->readMode !== self::MODE_PASSIVE && $this->read()) {
            return;
        }

        if ($this->buildMode !== self::MODE_PASSIVE && $this->build()) {
            return;
        }

        if ($this->defaultMode !== self::MODE_PASSIVE) {
            $this->default();
            return;
        }

        throw new LogicException('Could not initialize Hydrable `' . $this->getFriendlyIdentifier() . '`');
    }

    public function initialized(): bool
    {
        return $this->initialized;
    }

    /**
     * @return T
     */
    public function get(): mixed
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->value;
    }

    /**
     * @param list<array-key> $path
     */
    public function getNested(array $path): mixed
    {
        return Arrays::getNested($this->get(), $path);
    }

    /**
     * @param T $value
     */
    public function set(mixed $value, mixed $stamp = null): void
    {
        if (!$this->initialized) {
            throw new LogicException('Cannot write to uninitialized Hydrable `' . $this->getFriendlyIdentifier() . '`');
        }

        if ($this->ignoreSet) {
            return;
        }

        $this->applyValue($value, $stamp);
    }

    /**
     * @param list<array-key> $path
     */
    public function setNested(array $path, mixed $value, mixed $stamp = null): void
    {
        $topValue = $this->get();

        Arrays::setNested($topValue, $path, $value);

        $this->set($topValue, $stamp);
    }

    public function delete(): void
    {
        $this->value = null;
        $this->stamp = null;

        $this->initialized = false;
        $this->pendingWrite = false;
        $this->stampValidated = false;

        $this->store?->delete($this->key);
    }

    /**
     * @param list<array-key> $path
     */
    public function deleteNested(array $path): void
    {
        $value = $this->get();

        Arrays::deleteNested($value, $path);

        $this->set($value);
    }

    public function default(bool $deferred = false): void
    {
        $this->initialized = true;

        $this->applyValue($this->default, null, $deferred);
    }

    public function build(): bool
    {
        if ($closure = $this->closures['build']) {
            if ($this->writeMode !== self::MODE_PASSIVE) {
                $this->store?->lock($this->key);
            }

            $stopwatchPeriod = $this->stopwatch?->start('core.hydrable.build');

            $stamp = null;

            try {
                $value = $this->supportsStamping
                    ? $closure(stamp: $stamp)
                    : $closure()
                ;
            } finally {
                $stopwatchPeriod?->stop();
            }

            $this->initialized = true;

            $this->applyValue($value, $stamp);

            if ($this->writeMode !== self::MODE_PASSIVE) {
                $this->store?->unlock($this->key);
            }

            return true;
        } else {
            return false;
        }
    }

    public function write(): bool
    {
        if ($this->store) {
            $stopwatchPeriod = $this->stopwatch?->start('core.hydrable.write');

            try {
                $serialized = $this->serialize();

                $result = $this->store->set($this->key, $this->path, $serialized);

                $this->pendingWrite = false;
            } finally {
                $stopwatchPeriod?->stop();
            }

            return $result;
        }

        return false;
    }

    public function read(): bool
    {
        if ($this->store) {
            $stopwatchPeriod = $this->stopwatch?->start('core.hydrable.read');

            try {
                $serialized = $this->store->get($this->key, $this->path);

                if (is_array($serialized)) {
                    return $this->unserialize($serialized);
                }
            } catch (RuntimeException) {
                return false;
            } finally {
                $stopwatchPeriod?->stop();
            }
        }

        return false;
    }

    public function commit(): void
    {
        if ($this->pendingWrite) {
            $this->write();
        }
    }

    public function valid(): ?bool
    {
        if (!$this->supportsStamping) {
            return null;
        }

        if ($this->stampValidated) {
            return true;
        }

        $this->get();

        if ($this->stampValidated) {
            return true;
        }

        if ($this->stampValid($this->stamp)) {
            $this->stampValidated = true;

            return true;
        }

        return false;
    }

    /**
     * @param T $value
     */
    private function applyValue(mixed $value, mixed $stamp = null, bool $deferred = false): void
    {
        $this->value = $value;

        if ($stamp !== null) {
            $this->stamp = $stamp;
            $this->stampValidated = true;
        } else {
            $this->stampValidated = false;
        }

        if ($this->writeMode !== self::MODE_PASSIVE && $this->store) {
            if ($stamp !== null && $this->getStoredStamp() === $stamp) {
                return;
            }

            if (
                $deferred ||
                (
                    $this->writeMode === self::MODE_DEFERRED &&
                    !$this->pendingWrite
                )
            ) {
                $this->pendingWrite = true;
            } else {
                $this->write();
            }
        }
    }

    private function serialize(): array
    {
        if (!$this->initialized) {
            throw new InvalidArgumentException('Cannot serialize uninitialized Hydrable `' . $this->getFriendlyIdentifier() . '`');
        }

        $value = $this->value;

        if (gettype($value) !== gettype($this->default)) {
            throw new RuntimeException('Type of Hydrable `' . $this->getFriendlyIdentifier() . '` does not match default');
        }

        $this->filterValue('write', $value);

        return [
            self::DATA_STAMP => $this->stamp,
            self::DATA_VALUE => $value,
        ];
    }

    private function unserialize(array $data): bool
    {
        if (
            !array_key_exists(self::DATA_STAMP, $data) ||
            !array_key_exists(self::DATA_VALUE, $data)
        ) {
            throw new RuntimeException('Corrupted structure for Hydrable `' . $this->getFriendlyIdentifier() . '`');
        }

        if (
            $this->supportsStamping &&
            $this->validateStampMode !== self::MODE_PASSIVE
        ) {
            $stampValidated = true;

            if (!$this->stampValid($data[self::DATA_STAMP])) {
                return false;
            }
        } else {
            $stampValidated = false;
        }

        $value = $data[self::DATA_VALUE];

        $this->filterValue('read', $value);

        if (gettype($value) !== gettype($this->default)) {
            throw new RuntimeException('Type of unserialized Hydrable `' . $this->getFriendlyIdentifier() . '`does not match default');
        }

        $this->value = $value;
        $this->stamp = $data[self::DATA_STAMP];

        $this->initialized = true;
        $this->stampValidated = $stampValidated;

        return true;
    }

    private function stampValid(mixed $stamp): bool
    {
        if ($stamp === null) {
            return false;
        }

        if ($closure = $this->closures['validateStamp']) {
            if (!$closure($stamp)) {
                return false;
            }
        }

        return true;
    }

    private function getStoredStamp(): mixed
    {
        return $this->store->get($this->key, $this->path)[self::DATA_STAMP] ?? null;
    }

    private function filterValue(string $type, mixed &$value): void
    {
        if ($this->filters[$type] !== null) {
            $value = $this->filters[$type]($value);
        }
    }

    private function getFriendlyIdentifier(): string
    {
        if ($this->key !== null) {
            return $this->key . '[' . implode(', ', $this->path) . ']';
        } else {
            return '';
        }
    }
}
