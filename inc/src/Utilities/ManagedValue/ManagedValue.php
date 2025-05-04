<?php

declare(strict_types=1);

namespace MyBB\Utilities\ManagedValue;

use InvalidArgumentException;
use LogicException;
use MyBB\Stopwatch\Period;
use MyBB\Stopwatch\Stopwatch;
use MyBB\Utilities\Arrays;
use ReflectionFunction;
use RuntimeException;

/**
 * A self-managed value cell.
 *
 * Handles initialization, and read/write-through caching with validation.
 *
 * @template T of mixed
 */
class ManagedValue
{
    /**
     * Action performed instantly.
     */
    final public const MODE_IMMEDIATE = 2;

    /**
     * Action performed automatically when needed.
     */
    final public const MODE_DEFERRED = 4;

    /**
     * Action performed through manual method calls only.
     */
    final public const MODE_PASSIVE = 8;


    private const DATA_STAMP = 'stamp';
    private const DATA_VALUE = 'value';


    /**
     * The default value.
     *
     * If set, used as a reference for type validation.
     *
     * @var T
     */
    private readonly mixed $default;

    /**
     * @var array<string, array{
     *   build: ?callable(mixed &$stamp): T,
     *   stampValidation: ?callable(mixed $stamp): bool,
     * }
     */
    private array $callables = [
        'build' => null,
        'stampValidation' => null,
    ];

    /**
     * @var array<string, array{
     *   save: ?callable(T $value): T,
     *   load: ?callable(T $value): T,
     * }
     */
    private array $filters = [
        'save' => null,
        'load' => null,
    ];

    /**
     * @var self::MODE_DEFERRED | self::MODE_PASSIVE
     */
    private int $defaultMode = self::MODE_PASSIVE;

    /**
     * @var self::MODE_DEFERRED | self::MODE_PASSIVE
     */
    private int $buildMode = self::MODE_PASSIVE;

    /**
     * @var self::MODE_*
     */
    private int $saveMode = self::MODE_DEFERRED;

    /**
     * @var self::MODE_DEFERRED | self::MODE_PASSIVE
     */
    private int $loadMode = self::MODE_DEFERRED;

    /**
     * @var self::MODE_IMMEDIATE | self::MODE_PASSIVE
     */
    private int $stampValidationMode = self::MODE_PASSIVE;

    /**
     * Whether calls to `set()`, `setNested()`, and `deleteNested()` will be effective.
     */
    private bool $settersEnabled = true;

    private bool $buildCallableProvidesStamp = false;

    /**
     * Whether the held value has been set.
     */
    private bool $initialized = false;

    /**
     * The current held value.
     *
     * @var ?T
     */
    private mixed $value;

    /**
     * A stamp provided or loaded for the held value.
     *
     * A non-null stamp will be validated in the configured callback to check for stale values.
     */
    private mixed $stamp = null;

    /**
     * The result of the stamp validation callback for the held stamp.
     */
    private ?bool $stampValid = null;

    /**
     * @see self::$saveMode
     */
    private bool $uncommittedDeferredSave = false;

    /**
     * @param ?NestedStoreInterface $store The Store to use for saving and loading the value.
     * @param ?array $path The path to use for saving and loading the value in the Store.
     * @param ?Stopwatch $stopwatch A Stopwatch instance to record performance with.
     */
    public function __construct(
        private readonly ?NestedStoreInterface $store = null,
        private readonly ?array $path = null,
        private readonly ?Stopwatch $stopwatch = null,
    ) {
        if ($store !== null && $path === null) {
            throw new LogicException('Cannot use Store without a path for ' . $this->getName());
        }
    }

    /**
     * @pure
     */
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


    /**
     * Configures the default value.
     *
     * @param T $value The default value.
     * @param self::MODE_DEFERRED | self::MODE_PASSIVE $mode When to use the default value.
     */
    public function withDefault(mixed $value, int $mode = self::MODE_DEFERRED): self
    {
        if ($value === null) {
            throw new InvalidArgumentException('Default value for ' . $this->getName() . ' cannot be `null`');
        }

        $this->default = $value;

        $this->defaultMode = $mode;

        return $this;
    }

    /**
     * Configures building the value.
     *
     * @param ?callable(mixed &$stamp): T $callback Builds the value, optionally setting a corresponding stamp.
     * @param self::MODE_DEFERRED | self::MODE_PASSIVE $mode When to build and use the value.
     */
    public function withBuild(?callable $callback = null, int $mode = self::MODE_DEFERRED): self
    {
        $this->callables['build'] = $callback;

        $this->buildCallableProvidesStamp = $callback !== null && self::providesStamp($callback);

        $this->buildMode = $mode;

        return $this;
    }

    /**
     * Configures saving the value in the Store.
     *
     * @param ?callable(T $value): T $callback Returns the value transformed for storage.
     * @param self::MODE_* $mode When to save data in the store after changes.
     */
    public function withSave(?callable $callback = null, int $mode = self::MODE_DEFERRED): self
    {
        $this->filters['save'] = $callback;

        $this->saveMode = $mode;

        return $this;
    }

    /**
     * Configures loading the value from the Store.
     *
     * @param ?callable(T $value): T $callback Returns the hydrated value.
     * @param self::MODE_DEFERRED | self::MODE_PASSIVE $mode When to attempt to use the stored value.
     */
    public function withLoad(?callable $callback = null, int $mode = self::MODE_DEFERRED): self
    {
        $this->filters['load'] = $callback;

        $this->loadMode = $mode;

        return $this;
    }

    /**
     * Configures validating the stored value using the stamp.
     *
     * If `$mode` is `self::MODE_IMMEDIATE` and the callbacks returns `false`, the stored value is not loaded.
     *
     * @param ?callable(mixed $stamp): bool $callback Determines whether the stamp is valid.
     * @param self::MODE_IMMEDIATE | self::MODE_PASSIVE $mode When to validate the stored stamp.
     */
    public function withStampValidation(?callable $callback = null, int $mode = self::MODE_IMMEDIATE): self
    {
        $this->callables['stampValidation'] = $callback;

        $this->stampValidationMode = $mode;

        return $this;
    }

    /**
     * Whether to respect direct modification calls (`set()`, `setNested()`, and `deleteNested()`).
     */
    public function withSettersEnabled(?bool $enabled = null): self
    {
        $this->settersEnabled = $enabled;

        return $this;
    }


    /**
     * Sets the held value by loading, building, or defaulting, according to the respective configured modes.
     *
     * @throws LogicException if no means of implicit initialization are available.
     */
    public function initialize(): void
    {
        if ($this->loadMode !== self::MODE_PASSIVE && $this->load()) {
            return;
        }

        if ($this->buildMode !== self::MODE_PASSIVE) {
            $this->build();
            return;
        }

        if ($this->defaultMode !== self::MODE_PASSIVE) {
            $this->default();
            return;
        }

        throw new LogicException('Could not initialize ' . $this->getName());
    }

    /**
     * Whether a value is currently set.
     *
     * @pure
     */
    public function initialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Returns the held value.
     *
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
     * Returns the member at the specified array path from the held value, or `null` if not found.
     *
     * @param list<array-key> $path
     */
    public function getNested(array $path): mixed
    {
        return Arrays::getNested($this->get(), $path);
    }

    /**
     * Sets the held value.
     *
     * @param T $value
     * @param mixed $stamp The stamp for the resulting held value.
     */
    public function set(mixed $value, mixed $stamp = null): void
    {
        if (!$this->settersEnabled) {
            return;
        }

        $this->initialized = true;

        $this->applyValue($value, $stamp);
    }

    /**
     * Sets the member at the specified array path in the held value.
     *
     * @param list<array-key> $path
     * @param mixed $stamp The stamp for the resulting held value.
     */
    public function setNested(array $path, mixed $value, mixed $stamp = null): void
    {
        $topValue = $this->get();

        Arrays::setNested($topValue, $path, $value);

        $this->set($topValue, $stamp);
    }

    /**
     * Removes the specified array path key from the held value.
     *
     * @param list<array-key> $path
     * @param mixed $stamp The stamp for the resulting held value.
     */
    public function deleteNested(array $path, mixed $stamp = null): void
    {
        $value = $this->get();

        Arrays::deleteNested($value, $path);

        $this->set($value, $stamp);
    }

    /**
     * Sets the held value to the default value.
     */
    public function default(bool $deferred = false): void
    {
        if (!isset($this->default)) {
            throw new LogicException('Cannot call `' . __FUNCTION__ . '()` on ' . $this->getName() . ' with no default value');
        }

        $this->initialized = true;

        $this->applyValue($this->default, null, $deferred);
    }

    /**
     * Sets the held value to the result of the build callback.
     */
    public function build(): void
    {
        if ($this->callables['build'] === null) {
            throw new LogicException('Cannot call `' . __FUNCTION__ . '()` on ' . $this->getName() . ' with no build callback');
        }

        $callable = $this->callables['build'];

        if ($this->saveMode !== self::MODE_PASSIVE) {
            $this->store?->lock($this->path);
        }

        $stopwatchPeriod = $this->startStopwatchPeriod(__FUNCTION__);

        $stamp = null;

        try {
            $value = $this->buildCallableProvidesStamp
                ? $callable(stamp: $stamp)
                : $callable()
            ;
        } finally {
            $stopwatchPeriod?->stop();
        }

        $this->initialized = true;

        $this->applyValue($value, $stamp);

        if ($this->saveMode !== self::MODE_PASSIVE) {
            $this->store?->unlock($this->path);
        }
    }

    /**
     * Saves the held value and stamp in the Store.
     *
     * @throws LogicException if there is no stored value, and no means of implicit initialization are available.
     */
    public function save(): bool
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        if ($this->store) {
            $stopwatchPeriod = $this->startStopwatchPeriod(__FUNCTION__);

            try {
                $serialized = $this->getSerialized();

                $result = $this->store?->set($this->path, $serialized);

                $this->uncommittedDeferredSave = false;
            } finally {
                $stopwatchPeriod?->stop();
            }

            return $result;
        }

        return false;
    }

    /**
     * Saves uncommitted changes to held value and stamp in the Store.
     */
    public function commit(): bool
    {
        if ($this->uncommittedDeferredSave) {
            return $this->save();
        }

        return false;
    }

    /**
     * Populates the held value and stamp from the Store.
     */
    public function load(): bool
    {
        if ($this->store) {
            $stopwatchPeriod = $this->startStopwatchPeriod(__FUNCTION__);

            try {
                $serialized = $this->store?->get($this->path);

                if (is_array($serialized)) {
                    return $this->hydrate($serialized, $validateStopwatchPeriod);
                }
            } finally {
                $stopwatchPeriod?->stop();
                $stopwatchPeriod?->subtract($validateStopwatchPeriod ?? 0);
            }
        }

        return false;
    }

    /**
     * Whether the held stamp validates successfully according to the configured callback.
     *
     * Performs initialization if no value is held.
     */
    public function stampValid(): bool
    {
        if ($this->callables['stampValidation'] === null) {
            throw new LogicException('Cannot call `' . __FUNCTION__ . '()` on ' . $this->getName() . ' with no validation callback');
        }

        if ($this->stampValid === null) {
            if (!$this->initialized) {
                $this->get();
            }

            $this->stampValid = $this->stampValidates($this->stamp);
        }

        return $this->stampValid;
    }

    /**
     * Removes the held value from memory and the Store.
     */
    public function clear(): void
    {
        $this->value = null;
        $this->stamp = null;

        $this->initialized = false;
        $this->uncommittedDeferredSave = false;
        $this->stampValid = null;

        $this->store?->delete($this->path);
    }

    /**
     * Sets the held value and stamp and propagates the change.
     *
     * @param T $value
     * @param bool $deferred Whether to defer saving.
     */
    private function applyValue(mixed $value, mixed $stamp = null, bool $deferred = false): void
    {
        if (isset($this->default)) {
            if (gettype($value) !== gettype($this->default)) {
                throw new RuntimeException('Cannot set value of ' . $this->getName() . ' with different type than default');
            }
        }

        $this->value = $value;
        $this->stampValid = null;

        if ($stamp !== null) {
            $this->stamp = $stamp;
        }

        if (
            $this->store &&
            $this->saveMode !== self::MODE_PASSIVE
        ) {
            if (
                $deferred ||
                (
                    $this->saveMode === self::MODE_DEFERRED &&
                    !$this->uncommittedDeferredSave
                )
            ) {
                $this->uncommittedDeferredSave = true;
            } else {
                $this->save();
            }
        }
    }

    /**
     * Returns a storable array representation of the value and stamp held in memory.
     *
     * @return array<string, T|mixed>
     *
     * @throws LogicException if there is no stored value.
     */
    private function getSerialized(): array
    {
        if (!$this->initialized) {
            throw new LogicException('Cannot serialize uninitialized ' . $this->getName());
        }

        $value = $this->value;

        if (isset($this->default)) {
            if (gettype($value) !== gettype($this->default)) {
                throw new RuntimeException('Type of ' . $this->getName() . ' does not match default');
            }
        }

        $value = $this->getFilteredValue('save', $value);

        return [
            self::DATA_STAMP => $this->stamp,
            self::DATA_VALUE => $value,
        ];
    }

    /**
     * Sets the held value and stamp from a storable array representation.
     *
     * @param array<string, T|mixed> $data
     * @param-out ?Period &$validateStopwatchPeriod The duration of the stamp validation.
     * @return bool
     *   `true` on success;
     *   `false` on invalid stamp, corrupted data, or value type different from default.
     */
    private function hydrate(array $data, ?Period &$validateStopwatchPeriod = null): bool
    {
        if (
            !array_key_exists(self::DATA_STAMP, $data) ||
            !array_key_exists(self::DATA_VALUE, $data)
        ) {
            return false;
        }

        if (
            $this->callables['stampValidation'] !== null &&
            $this->stampValidationMode !== self::MODE_PASSIVE
        ) {
            if (!$this->stampValidates($data[self::DATA_STAMP], $validateStopwatchPeriod)) {
                return false;
            }

            $stampValid = true;
        } else {
            $stampValid = null;
        }

        $value = $data[self::DATA_VALUE];

        $value = $this->getFilteredValue('load', $value);

        if (isset($this->default)) {
            if (gettype($value) !== gettype($this->default)) {
                return false;
            }
        }

        $this->value = $value;
        $this->stamp = $data[self::DATA_STAMP];

        $this->initialized = true;
        $this->stampValid = $stampValid;

        return true;
    }

    /**
     * Whether the given stamp validates successfully according to the validation callback.
     *
     * @param-out ?Period &$stopwatchPeriod The duration of the stamp validation.
     */
    private function stampValidates(mixed $stamp, ?Period &$stopwatchPeriod = null): bool
    {
        if ($stamp === null) {
            return false;
        }

        $callable = $this->callables['stampValidation'];

        $stopwatchPeriod = $this->startStopwatchPeriod(__FUNCTION__);

        try {
            return $callable($stamp);
        } finally {
            $stopwatchPeriod?->stop();
        }
    }

    /**
     * Returns the given value processed by the configured callback.
     *
     * @param T $value
     * @return T
     */
    private function getFilteredValue(string $type, mixed $value): mixed
    {
        if ($this->filters[$type] !== null) {
            return $this->filters[$type]($value);
        } else {
            return $value;
        }
    }

    /**
     * Returns a new measurement for a member's lifecycle event (if configured).
     */
    private function startStopwatchPeriod(string $action): ?Period
    {
        return $this->stopwatch?->start(
            $this->getFriendlyPath(),
            group: 'managedValue.' . $action,
        );
    }

    /**
     * Returns an informal identifier for debugging.
     *
     * @pure
     */
    private function getName(): string
    {
        $name = 'ManagedValue';

        if ($this->store !== null) {
            $name .= ' `' . $this->getFriendlyPath() . '`';
        }

        return $name;
    }

    /**
     * Returns an informal representation of the array path used for storage.
     *
     * @pure
     */
    private function getFriendlyPath(): string
    {
        if ($this->store !== null) {
            return
                implode(
                    '',
                    array_map(
                        fn ($string) => $string . '/',
                        $this->store->getPath(),
                    ),
                ) .
                implode(' → ', $this->path ?? []);
        } else {
            return spl_object_hash($this);
        }
    }
}
