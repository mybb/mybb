<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Utilities;

use MyBB\Utilities\Decorator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use MyBB\Tests\Unit\Utilities\Fixtures\Component\Component;
use MyBB\Tests\Unit\Utilities\Fixtures\Component\Decorator\ComponentDecorator;
use MyBB\Tests\Unit\Utilities\Fixtures\Component\Decorator\ComponentDecoratorA;
use MyBB\Tests\Unit\Utilities\Fixtures\Component\Decorator\ComponentDecoratorB;

final class DecoratorTest extends TestCase
{
    public static function decoratorReturnCases(): array
    {
        return [
            [
                'decorated' => new ComponentDecoratorA(
                    new Component(),
                ),
                'decorator' => ComponentDecoratorA::class,
                'expectedClassInstances' => [
                    ComponentDecoratorA::class,
                ],
            ],
            [
                'decorated' => new ComponentDecoratorB(
                    new ComponentDecoratorA(
                        new Component(),
                    ),
                ),
                'decorator' => ComponentDecoratorB::class,
                'expectedClassInstances' => [
                    ComponentDecoratorB::class,
                ],
            ],
            [
                'decorated' => new ComponentDecoratorB(
                    new ComponentDecoratorA(
                        new ComponentDecoratorA(
                            new Component(),
                        ),
                    ),
                ),
                'decorator' => ComponentDecoratorA::class,
                'expectedClassInstances' => [
                    ComponentDecoratorA::class,
                    ComponentDecoratorA::class,
                ],
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'decorator' => ComponentDecoratorA::class,
                'expectedClassInstances' => [],
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'decorator' => ComponentDecoratorA::class,
                'expectedClassInstances' => [
                    ComponentDecoratorA::class,
                ],
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorA::class,
                ]),
                'decorator' => ComponentDecoratorA::class,
                'expectedClassInstances' => [
                    ComponentDecoratorA::class,
                    ComponentDecoratorA::class,
                ],
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'decorator' => ComponentDecoratorA::class,
                'expectedClassInstances' => [
                    ComponentDecoratorA::class,
                ],
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'decorator' => ComponentDecoratorB::class,
                'expectedClassInstances' => [
                    ComponentDecoratorB::class,
                ],
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorA::class,
                ]),
                'decorator' => ComponentDecoratorA::class,
                'expectedClassInstances' => [
                    ComponentDecoratorA::class,
                    ComponentDecoratorA::class,
                ],
            ],
        ];
    }

    public static function decorationStatusCases(): array
    {
        return [
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'decorator' => ComponentDecoratorA::class,
                'expected' => false,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'decorator' => ComponentDecoratorA::class,
                'expected' => true,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'decorator' => ComponentDecoratorB::class,
                'expected' => false,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorA::class,
                ]),
                'decorator' => ComponentDecoratorA::class,
                'expected' => true,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'decorator' => ComponentDecoratorA::class,
                'expected' => true,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'decorator' => ComponentDecoratorB::class,
                'expected' => true,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorA::class,
                ]),
                'decorator' => ComponentDecoratorA::class,
                'expected' => true,
            ],
        ];
    }

    public static function nestedNavigationCases(): array
    {
        return [
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'expectedClassInstance' => Component::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorA::class,
                ]),
                'expectedClassInstance' => ComponentDecoratorA::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'expectedClassInstance' => ComponentDecoratorA::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorB::class,
                    ComponentDecoratorA::class,
                ]),
                'expectedClassInstance' => ComponentDecoratorB::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorA::class,
                ]),
                'expectedClassInstance' => ComponentDecoratorB::class,
            ],
        ];
    }

    public static function decoratedCallCases(): array
    {
        return [
            // undecoratedMethod()
            [
                'decorated' => new Component(),
                'method' => 'undecoratedMethod',
                'expected' => Component::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'method' => 'undecoratedMethod',
                'expected' => Component::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'method' => 'undecoratedMethod',
                'expected' => Component::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'method' => 'undecoratedMethod',
                'expected' => Component::class,
            ],

            // decoratedMethod()
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'method' => 'cascadeMethod',
                'expected' => Component::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'method' => 'decoratedMethod',
                'expected' => ComponentDecoratorA::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'method' => 'decoratedMethod',
                'expected' => ComponentDecoratorB::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorA::class,
                ]),
                'method' => 'decoratedMethod',
                'expected' => ComponentDecoratorA::class,
            ],

            // addedCommonMethod()
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'method' => 'addedCommonMethod',
                'expected' => null,
                'exception' => \BadMethodCallException::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'method' => 'addedCommonMethod',
                'expected' => ComponentDecoratorA::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'method' => 'addedCommonMethod',
                'expected' => ComponentDecoratorB::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorA::class,
                ]),
                'method' => 'addedCommonMethod',
                'expected' => ComponentDecoratorA::class,
            ],

            // addedMethodA()
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'method' => 'addedMethodA',
                'expected' => null,
                'exception' => \BadMethodCallException::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'method' => 'addedMethodA',
                'expected' => ComponentDecoratorA::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'method' => 'addedMethodA',
                'expected' => ComponentDecoratorA::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorA::class,
                ]),
                'method' => 'addedMethodA',
                'expected' => ComponentDecoratorA::class,
            ],

            // addedMethodB()
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'method' => 'addedMethodB',
                'expected' => null,
                'exception' => \BadMethodCallException::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'method' => 'addedMethodB',
                'expected' => null,
                'exception' => \BadMethodCallException::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'method' => 'addedMethodB',
                'expected' => ComponentDecoratorB::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorA::class,
                ]),
                'method' => 'addedMethodB',
                'expected' => ComponentDecoratorB::class,
            ],

            // immediateDecoratedMethod()
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'method' => 'immediateDecoratedMethod',
                'expected' => null,
                'exception' => \BadMethodCallException::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'method' => 'immediateDecoratedMethod',
                'expected' => Component::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'method' => 'immediateDecoratedMethod',
                'expected' => ComponentDecoratorA::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorB::class,
                    ComponentDecoratorA::class,
                ]),
                'method' => 'immediateDecoratedMethod',
                'expected' => ComponentDecoratorB::class,
            ],

            // cascadeMethod()
            [
                'decorated' => new Component(),
                'method' => 'cascadeMethod',
                'expected' => Component::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'method' => 'cascadeMethod',
                'expected' => Component::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                ]),
                'method' => 'cascadeMethod',
                'expected' => Component::class,
            ],
            [
                'decorated' => ComponentDecorator::decorate(new Component(), [
                    ComponentDecoratorA::class,
                    ComponentDecoratorB::class,
                ]),
                'method' => 'cascadeMethod',
                'expected' => Component::class,
            ],

            // protectedMethod()
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'method' => 'protectedMethod',
                'expected' => null,
                'exception' => \BadMethodCallException::class,
            ],

            // privateMethod()
            [
                'decorated' => ComponentDecorator::decorate(new Component()),
                'method' => 'privateMethod',
                'expected' => null,
                'exception' => \BadMethodCallException::class,
            ],
        ];
    }

    public static function optionsCases(): array
    {
        return [
            [
                'decorated' => function () {
                    $decorated = new ComponentDecoratorB(new Component());

                    $decorated->setOption('value');

                    return $decorated;
                },
                'method' => 'optionDependentMethod',
                'expected' => 'value',
            ],
            [
                'decorated' => function () {
                    $decorator = new ComponentDecoratorB();

                    $decorator->setOption('value');

                    $decorated = ComponentDecorator::decorate(new Component(), [
                        ComponentDecoratorA::class,
                        $decorator,
                    ]);

                    return $decorated;
                },
                'method' => 'optionDependentMethod',
                'expected' => 'value',
            ],
            [
                'decorated' => function () {
                    $decorated = ComponentDecorator::decorate(new Component(), [
                        ComponentDecoratorA::class,
                        ComponentDecoratorB::class,
                    ]);

                    ComponentDecoratorB::getInstancesDecorating($decorated)[0]->setOption('value');

                    return $decorated;
                },
                'method' => 'optionDependentMethod',
                'expected' => 'value',
            ],
        ];
    }

    public static function exceptionCases(): array
    {
        return [
            // disallowed abstract class call
            [
                'callback' => fn () => Decorator::decorate(new Component()),
                'exception' => \BadMethodCallException::class,
            ],
            [
                'callback' => fn () => Decorator::decorates(new Component()),
                'exception' => \BadMethodCallException::class,
            ],
            [
                'callback' => fn () => Decorator::getInstancesDecorating(
                    ComponentDecorator::decorate(new Component())
                ),
                'exception' => \BadMethodCallException::class,
            ],

            // disallowed base decorator class call
            [
                'callback' => fn () => ComponentDecorator::decorates(new Component()),
                'exception' => \BadMethodCallException::class,
            ],

            // disallowed concrete decorator class call
            [
                'callback' => fn () => ComponentDecoratorA::decorate(new Component(), [
                    ComponentDecoratorB::class,
                ]),
                'exception' => \BadMethodCallException::class,
            ],

            // invalid decorator
            [
                'callback' => fn () => ComponentDecorator::decorate(new Component(), [
                    'nonexistentClass',
                ]),
                'exception' => \InvalidArgumentException::class,
            ],
            [
                'callback' => fn () => ComponentDecorator::decorate(new Component(), [
                    new \stdClass(),
                ]),
                'exception' => \InvalidArgumentException::class,
            ],
            [
                'callback' => fn () => ComponentDecorator::decorate(new Component(), [
                    \stdClass::class,
                ]),
                'exception' => \InvalidArgumentException::class,
            ],
            [
                'callback' => fn () => ComponentDecorator::decorate(
                    new Component(),
                    [
                        Decorator::class,
                    ],
                ),
                'exception' => \InvalidArgumentException::class,
            ],

            // call to protected members via magic methods
            [
                'callback' => function () {
                    $decoratedComponent = ComponentDecorator::decorate(new Component());

                    $decoratedComponent->getDecorated();
                },
                'exception' => \BadMethodCallException::class,
            ],

            // assigning decorator instance to more than one stack
            [
                'callback' => function () {
                    $decorator = new ComponentDecoratorB();

                    ComponentDecorator::decorate(new Component(), [
                        $decorator,
                    ]);
                    ComponentDecorator::decorate(new Component(), [
                        $decorator,
                    ]);
                },
                'exception' => \LogicException::class,
            ],
        ];
    }

    /**
     * @param class-string<Decorator> $decorator
     */
    #[DataProvider('decoratorReturnCases')]
    public function testDecoratorReturn(Decorator $decorated, string $decorator, $expectedClassInstances)
    {
        $result = $decorator::getInstancesDecorating($decorated);

        static::assertIsArray($result);
        static::assertContainsOnlyObject($result);

        $classes = array_map('get_class', $result);

        static::assertSame($expectedClassInstances, $classes);
    }

    /**
     * @param class-string<Decorator> $decorator
     */
    #[DataProvider('decorationStatusCases')]
    public function testDecorationStatus(Decorator $decorated, string $decorator, bool $expected)
    {
        $result = $decorator::decorates($decorated);

        static::assertSame($expected, $result);
    }

    /**
     * @param class-string<Decorator> $expectedClassInstance
     */
    #[DataProvider('nestedNavigationCases')]
    public function testNestedNavigation(ComponentDecorator $decorated, string $expectedClassInstance)
    {
        $result = $decorated->getDecoratedObject();

        static::assertInstanceOf($expectedClassInstance, $result);
    }

    #[DataProvider('decoratedCallCases')]
    public function testDecoratedCall(object $decorated, string $method, ?string $expected = null, ?string $exception = null)
    {
        if ($exception !== null) {
            $this->expectException($exception);
        }

        $result = $decorated->$method();

        if ($expected !== null) {
            static::assertSame($expected, $result);
        }
    }

    #[DataProvider('optionsCases')]
    public function testOptions(object $decorated, string $method, ?string $expected = null, ?string $exception = null)
    {
        if ($exception !== null) {
            $this->expectException($exception);
        }

        if (is_callable($decorated)) {
            $decorated = $decorated();
        }

        $result = $decorated->$method();

        if ($expected !== null) {
            static::assertSame($expected, $result);
        }
    }

    /**
     * @param class-string<\Exception> $exception
     */
    #[DataProvider('exceptionCases')]
    public function testException(callable $callback, string $exception)
    {
        $this->expectException($exception);

        $callback();
    }

    public function testDecorationException()
    {
        $method = 'noopMethod';
        $class = ComponentDecoratorA::class;

        $this->expectExceptionMessage('`' . $method . '()` cannot be called with `' . $class . '`');

        $decorated = ComponentDecorator::decorate(new Component(), [
            ComponentDecoratorA::class,
        ]);

        $decorated->$method();
    }
}
