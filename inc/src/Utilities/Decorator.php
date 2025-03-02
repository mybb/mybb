<?php

declare(strict_types=1);

namespace MyBB\Utilities;

use BadMethodCallException;
use Illuminate\Container\Container;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use SplObjectStorage;

/**
 * Provides stack management functionality, helpers, and magic methods for base and concrete decorators.
 *
 * @template TTarget of object
 * @template TDecorator of static
 */
abstract class Decorator
{
    /**
     * The abstract decorator class.
     */
    private const TYPE_ABSTRACT = 2;

    /**
     * A general decorator class directly extending the abstract class, representing the decoration stack.
     * Associated with, and implementing the same interface as, the innermost decorated class.
     */
    private const TYPE_BASE = 4;

    /**
     * A specific decorator class, extending a base decorator class.
     */
    private const TYPE_CONCRETE = 8;

    private const MEMBER_TYPE_METHOD = 2;
    private const MEMBER_TYPE_PROPERTY = 4;

    /**
     * Cached class type.
     *
     * @var self::TYPE_*
     */
    private readonly int $type;


    /**
     * The plain object to be decorated.
     * Used in base decorator instances.
     *
     * @var ?TTarget
     */
    private readonly ?object $target;

    /**
     * The applied concrete decorator instances in descending priority order.
     * Used in base decorator instances.
     *
     * @var list<TDecorator>
     */
    private array $decorators = [];

    /**
     * Cached public members of objects in the decoration stack.
     * Used in base decorator instances.
     *
     * @var SplObjectStorage<static, array<self::MEMBER_TYPE_*, string[]>>
     */
    private SplObjectStorage $publicMembers;


    /**
     * The base decorator instance, in which the concrete decorator instance is stacked.
     * Used in concrete decorator instances.
     */
    private readonly self $baseDecorator;


    /**
     * Decorates an object with concrete decorators.
     *
     * @param object<TTarget> $object
     * @param list<TTarget|class-string<TDecorator>> $decorators
     *   Class names or instances of concrete decorators in ascending priority order.
     * @return TTarget&TDecorator A base decorator instance.
     *
     * @note Class references are initialized as objects.
     *   The provided decorators are assigned types & bindings,
     *   enabling correct functionality if the parent constructor (self::__construct()`) will not be called.
     * @see self::__construct()
     */
    final public static function decorate(object $object, array $decorators = []): static
    {
        $type = static::getType();

        if ($type !== self::TYPE_BASE) {
            throw new BadMethodCallException(
                '`' . __METHOD__ . '()` must be called on a base decorator'
            );
        }

        if ($object instanceof self) {
            $baseDecorator = $object->getBaseDecorator();
        } else {
            $baseDecorator = new static($object);
        }

        foreach ($decorators as $decorator) {
            if (is_object($decorator)) {
                if (!is_subclass_of($decorator, static::class)) {
                    throw new InvalidArgumentException(
                        'Decorator `' . $decorator::class . '` must be a subclass of the base decorator'
                    );
                }

                if (isset($decorator->baseDecorator)) {
                    throw new LogicException(
                        'Decorator instance `' . $decorator::class . '` is already linked to a base decorator'
                    );
                }

                // assign type & bindings

                $decorator->type ??= self::TYPE_CONCRETE;
                $decorator->baseDecorator ??= $baseDecorator;

                $baseDecorator->addDecorator($decorator);
            } else {
                if (!class_exists($decorator)) {
                    throw new InvalidArgumentException(
                        'Decorator class `' . $decorator . '` not found'
                    );
                }

                if (!is_subclass_of($decorator, static::class)) {
                    throw new InvalidArgumentException(
                        'Decorator `' . $decorator . '` must be a subclass of the base decorator'
                    );
                }

                $container = Container::getInstance();

                try {
                    // assign type & bindings before executing the decorator's logic

                    $decorator = (new ReflectionClass($decorator))->newInstanceWithoutConstructor();

                    $decorator->type = self::TYPE_CONCRETE;
                    $decorator->baseDecorator = $baseDecorator;

                    $baseDecorator->addDecorator($decorator);

                    $container->call(
                        $decorator->__construct(...),
                        [
                            'baseDecorator' => $baseDecorator,
                        ],
                    );
                } catch (ReflectionException) {
                    $decorator = $container->make($decorator, [
                        'baseDecorator' => $baseDecorator,
                    ]);

                    $baseDecorator->addDecorator($decorator);
                }
            }
        }

        return $baseDecorator;
    }

    /**
     * Returns whether the concrete decorator class is in the specified decoration stack.
     */
    final public static function decorates(object $object): bool
    {
        if (static::getType() !== self::TYPE_CONCRETE) {
            throw new BadMethodCallException('`' . __METHOD__ . '()` must be called on concrete decorator');
        }

        return (
            is_subclass_of($object, self::class) &&
            in_array(
                static::class,
                $object->getBaseDecorator()->getDecoratorClasses(),
            )
        );
    }

    /**
     * Returns instance(s) of the concrete decorator class from the specified decoration stack.
     *
     * @return static[]
     */
    final public static function getInstancesDecorating(self $object): array
    {
        if (static::getType() !== self::TYPE_CONCRETE) {
            throw new BadMethodCallException('`' . __METHOD__ . '()` must be called on concrete decorator');
        }

        return $object->getBaseDecorator()->getDecoratorsByClass(static::class);
    }

    protected static function decoratedCallException(): LogicException
    {
        $class = static::class;
        $method = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        return new LogicException('`' . $method . '()` cannot be called with `' . $class . '`');
    }

    /**
     * @return class-string<self>
     */
    private static function getBaseDecoratorClass(): string
    {
        $class = static::class;

        while (($parent = get_parent_class($class)) !== self::class) {
            $class = $parent;
        }

        return $class;
    }

    /**
     * @return self::TYPE_*
     */
    private static function getType(): int
    {
        $class = static::class;

        if ($class === self::class) {
            return self::TYPE_ABSTRACT;
        } elseif (get_parent_class($class) === self::class) {
            return self::TYPE_BASE;
        } elseif (is_subclass_of($class, self::class)) {
            return self::TYPE_CONCRETE;
        } else {
            throw new InvalidArgumentException('Class `' . $class . '` is not a subclass of `Decorator`');
        }
    }

    /**
     * Depending on the class, creates an instance of:
     * - a concrete decorator, attaching it to a base decorator if one exists or can be created with the target object,
     * - a base decorator, with the provided target object.
     *
     * @note The type and bindings may also have been saved externally in `self::decorate()`.
     * @see self::decorate()
     */
    public function __construct(?object $object = null, ?self $baseDecorator = null)
    {
        $this->type ??= static::getType();

        if ($this->type === self::TYPE_CONCRETE) {
            if ($baseDecorator === null && $object !== null) {
                if ($object instanceof self) {
                    $baseDecorator = $object->getBaseDecorator();
                } else {
                    $baseDecoratorClass = static::getBaseDecoratorClass();

                    $baseDecorator = new $baseDecoratorClass($object);
                }

                $baseDecorator->addDecorator($this);
            }

            if ($baseDecorator !== null) {
                $this->baseDecorator ??= $baseDecorator;
            }
        } elseif ($this->type === self::TYPE_BASE) {
            if ($object === null || $object instanceof self || $baseDecorator !== null) {
                throw new InvalidArgumentException(
                    'Invalid arguments for base decorator constructor'
                );
            }

            $this->target = $object;
            $this->publicMembers = new SplObjectStorage();

            $this->publicMembers->attach($object, [
                self::MEMBER_TYPE_METHOD => [],
                self::MEMBER_TYPE_PROPERTY => [],
            ]);
        }
    }

    public function __call(string $name, array $arguments): mixed
    {
        $object = $this->getDecoratedWithPublicMember(self::MEMBER_TYPE_METHOD, $name);

        return $object->$name(...$arguments);
    }

    public function __get(string $name): mixed
    {
        $object = $this->getDecoratedWithPublicMember(self::MEMBER_TYPE_PROPERTY, $name);

        return $object->$name;
    }

    public function __set(string $name, mixed $value): void
    {
        $object = $this->getDecoratedWithPublicMember(self::MEMBER_TYPE_PROPERTY, $name, true);

        $object->$name = $value;
    }

    public function __isset(string $name): bool
    {
        $object = $this->getDecoratedWithPublicMember(self::MEMBER_TYPE_PROPERTY, $name, true);

        return isset($object->$name);
    }

    public function __unset(string $name): void
    {
        $object = $this->getDecoratedWithPublicMember(self::MEMBER_TYPE_PROPERTY, $name, true);

        unset($object->$name);
    }

    /**
     * Returns the immediate decorated object.
     *
     * When called on an instance of:
     * - a concrete decorator: returns the next object in the call stack in descending priority,
     * - a base decorator: returns the target object to be decorated.
     */
    final protected function getDecorated(): object
    {
        if ($this->type === self::TYPE_BASE) {
            return $this->getBaseDecorator()->target;
        } else {
            return $this->getBaseDecorator()->callStack($this)->current();
        }
    }

    /**
     * Determine the object in the decoration stack to forward magic function calls to.
     *
     * @param self::MEMBER_TYPE_* $memberType
     */
    private function getDecoratedWithPublicMember(int $memberType, string $name, bool $defaultToTarget = false): object
    {
        [$existsCallback, $reflection] = match ($memberType) {
            self::MEMBER_TYPE_METHOD => [
                method_exists(...),
                ReflectionMethod::class,
            ],
            self::MEMBER_TYPE_PROPERTY => [
                property_exists(...),
                ReflectionProperty::class,
            ],
        };

        $baseDecorator = $this->getBaseDecorator();

        // start at the top when called on a base decorator, otherwise after the called concrete decorator
        $after = $this->type === self::TYPE_CONCRETE ? $this : null;

        foreach ($baseDecorator->callStack($after) as $object) {
            if (
                $baseDecorator->publicMembers->contains($object) &&
                in_array($name, $baseDecorator->publicMembers[$object][$memberType])
            ) {
                return $object;
            }

            if (
                // the member exists (without magic method false positives)

                /*
                 * A class reference `$object::class` was previously necessary to prevent
                 * instantiating ReflectionMethod, which threw for private methods
                 * (https://github.com/php/php-src/pull/9640, https://github.com/php/php-src/pull/12127)
                 */
                $existsCallback($object, $name) &&
                (new $reflection($object, $name))->isPublic()
            ) {
                $members = $baseDecorator->publicMembers[$object];

                $members[$memberType][] = $name;

                $baseDecorator->publicMembers->attach($object, $members);

                return $object;
            }
        }

        if ($defaultToTarget === true) {
            return $baseDecorator->target;
        } else {
            $className = $baseDecorator->getTargetClassName();

            $memberName = match ($memberType) {
                self::MEMBER_TYPE_METHOD => 'method `' . $className . '::' . $name . '()`',
                self::MEMBER_TYPE_PROPERTY => 'property `' . $className . '::$' . $name . '`',
            };

            throw new BadMethodCallException('Undefined ' . $memberName);
        }
    }

    /**
     * Returns the name of the decorated class.
     * Used in base decorator instances.
     *
     * @return class-string
     */
    private function getTargetClassName(): string
    {
        return $this->target::class;
    }

    /**
     * Returns concrete decorator instances and the innermost object to be decorated.
     * Used in base decorator instances.
     *
     * @return iterable<TDecorator|TTarget>
     */
    private function callStack(?self $after = null): iterable
    {
        $consume = $after === null;

        foreach ($this->decorators as $object) {
            if ($consume === false) {
                if ($object === $after) {
                    $consume = true;
                }

                continue;
            }

            yield $object;
        }

        yield $this->target;
    }

    /**
     * Adds a concrete decorator instance to the decoration stack with top priority.
     * Used in base decorator instances.
     */
    private function addDecorator(self $object): void
    {
        array_unshift($this->decorators, $object);

        $this->publicMembers->attach($object, [
            self::MEMBER_TYPE_METHOD => [],
            self::MEMBER_TYPE_PROPERTY => [],
        ]);
    }

    /**
     * Returns concrete decorator classes present in the decoration stack.
     * Used in base decorator instances.
     *
     * @return class-string<static>[]
     */
    private function getDecoratorClasses(): array
    {
        return array_map('get_class', $this->decorators);
    }

    /**
     * Returns concrete decorator instances present in the decoration stack by class.
     * Used in base decorator instances.
     *
     * @param class-string<self> $class
     * @return static[]
     */
    private function getDecoratorsByClass(string $class): array
    {
        return array_values(
            array_filter(
                $this->decorators,
                fn (self $decorator) => $decorator::class === $class,
            )
        );
    }

    /**
     * Returns the associated base decorator instance.
     */
    private function getBaseDecorator(): self
    {
        return match ($this->type) {
            self::TYPE_BASE => $this,
            self::TYPE_CONCRETE => $this->baseDecorator ?? throw new LogicException(
                'Decorator `' . static::class . '` must be assigned to a decoration stack before usage'
            ),
            default => throw new BadMethodCallException(
                '`' . __METHOD__ . '` must be called on a subclass of `Decorator`'
            ),
        };
    }
}
