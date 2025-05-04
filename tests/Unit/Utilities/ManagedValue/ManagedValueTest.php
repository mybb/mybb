<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Utilities\ManagedValue;

use InvalidArgumentException;
use LogicException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use MyBB\Utilities\ManagedValue\ManagedValue;
use MyBB\Utilities\ManagedValue\NestedStoreInterface;
use MyBB\Utilities\ManagedValue\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use stdClass;

#[CoversClass(ManagedValue::class)]
class ManagedValueTest extends MockeryTestCase
{
    public static function valueCases(): array
    {
        return array_map(
            fn ($value) => [$value],
            [
                '',
                'abc',
                0,
                123,
                PHP_INT_MAX,
                -PHP_INT_MAX,
                0.0,
                123.345,
                -123.345,
                true,
                false,
                [123 => 345],
                new stdClass(),
                fn () => null,
                fopen('php://memory', 'r'),
            ],
        );
    }

    #region initialization
    #[TestWith([
        'default' => ManagedValue::MODE_DEFERRED,
        'build' => null,
        'load' => null,
        'expected' => 'default-value',
    ])]
    #[TestWith([
        'default' => ManagedValue::MODE_DEFERRED,
        'build' => ManagedValue::MODE_DEFERRED,
        'load' => null,
        'expected' => 'build-callback-value',
    ])]
    #[TestWith([
        'default' => ManagedValue::MODE_DEFERRED,
        'build' => ManagedValue::MODE_PASSIVE,
        'load' => null,
        'expected' => 'default-value',
    ])]
    #[TestWith([
        'default' => ManagedValue::MODE_DEFERRED,
        'build' => null,
        'load' => ManagedValue::MODE_DEFERRED,
        'expected' => 'load-callback-value',
    ])]
    #[TestWith([
        'default' => ManagedValue::MODE_DEFERRED,
        'build' => ManagedValue::MODE_DEFERRED,
        'load' => ManagedValue::MODE_DEFERRED,
        'expected' => 'load-callback-value',
    ])]
    #[TestWith([
        'default' => ManagedValue::MODE_DEFERRED,
        'build' => ManagedValue::MODE_DEFERRED,
        'load' => ManagedValue::MODE_PASSIVE,
        'expected' => 'build-callback-value',
    ])]
    #[TestWith([
        'default' => ManagedValue::MODE_DEFERRED,
        'build' => ManagedValue::MODE_PASSIVE,
        'load' => ManagedValue::MODE_PASSIVE,
        'expected' => 'default-value',
    ])]
    public function testInitializesWithCorrectPriority(?int $default, ?int $build, ?int $load, mixed $expected): void
    {
        if ($load !== null) {
            $nestedStoreStub = Mockery::mock(NestedStoreInterface::class)
                ->shouldIgnoreMissing()
                ->shouldReceive('get')
                    ->andReturn([
                        'stamp' => null,
                        'value' => 'stored-value',
                    ])
                ->getMock()
            ;
        }

        $managedValue = new ManagedValue($nestedStoreStub ?? null, ['test-managedValue']);

        foreach (['default', 'build', 'load'] as $type) {
            if ($$type !== null) {
                $managedValue->{'with' . ucfirst($type)}(
                    $type === 'default'
                        ? 'default-value'
                        : fn() => $type . '-callback-value',
                    $$type,
                );
            }
        }

        self::assertSame(
            false,
            $managedValue->initialized(),
        );


        $value = $managedValue->get();


        self::assertSame($expected, $value);

        self::assertSame(
            true,
            $managedValue->initialized(),
        );
    }

    #[DataProvider('valueCases')]
    public function testHeldValueAfterInitialization(mixed $value): void
    {
        foreach (['default', 'build', 'load'] as $type) {
            $nestedStoreStub = Mockery::mock(NestedStoreInterface::class)
                ->shouldIgnoreMissing()
                ->shouldReceive('get')
                ->andReturn([
                    'stamp' => null,
                    'value' => 'stored-value',
                ])
                ->getMock()
            ;

            $repository = new Repository(
                $nestedStoreStub,
            );

            $managedValue = $repository->create('test-managedValue');

            $managedValue
                ->withLoad(mode: ManagedValue::MODE_PASSIVE)
            ;

            $source = match ($type) {
                'default' => $value,
                'build',
                'load' => fn () => $value,
            };

            $method = 'with' . ucfirst($type);


            $managedValue->$method($source);

            self::assertSame(
                $value,
                $managedValue->get(),
                $method . '()',
            );
        }
    }


    #[TestWith(['default'])]
    #[TestWith(['build'])]
    #[TestWith(['load'])]
    public function testHeldValueAfterDirectSourceInitialization(string $method): void
    {
        $nestedStoreStub = Mockery::mock(NestedStoreInterface::class)
            ->shouldIgnoreMissing()
            ->shouldReceive('get')
            ->with(['test-managedValue'])
            ->andReturn([
                'stamp' => null,
                'value' => 'stored-value',
            ])
            ->getMock()
        ;

        $repository = new Repository(
            $nestedStoreStub,
        );

        $managedValue = $repository->create('test-managedValue');

        $source = match ($method) {
            'default' => 'test-value',
            'build',
            'load' => fn () => 'test-value',
        };

        $configurationMethod = 'with' . ucfirst($method);

        $managedValue
            ->withLoad(mode: ManagedValue::MODE_PASSIVE)
        ;

        $managedValue
            ->$configurationMethod($source, mode: ManagedValue::MODE_PASSIVE)
        ;


        self::assertSame(
            false,
            $managedValue->initialized(),
        );

        $managedValue->$method();

        self::assertSame(
            'test-value',
            $managedValue->get(),
        );
    }

    public function testInvalidDefaultValueException(): void
    {
        $managedValue = new ManagedValue();


        self::expectException(InvalidArgumentException::class);

        $managedValue
            ->withDefault(null)
        ;
    }

    #[TestWith(['build', RuntimeException::class])]
    #[TestWith(['load', LogicException::class])] // no effective initialization
    public function testInitializationWithIncompatibleTypeException(string $method, string $expectedException): void
    {
        if ($method === 'load') {
            $nestedStoreStub = Mockery::mock(NestedStoreInterface::class)
                ->shouldReceive('get')
                    ->with(['test-managedValue'])
                    ->andReturn([
                        'stamp' => null,
                        'value' => 'stored-value',
                    ])
                ->getMock()
            ;
        } else {
            $nestedStoreStub = null;
        }

        $repository = new Repository(
            $nestedStoreStub,
        );

        $managedValue = $repository->create('test-managedValue');

        $managedValue
            ->withDefault(123, mode: ManagedValue::MODE_PASSIVE)
            ->withBuild(mode: ManagedValue::MODE_PASSIVE)
            ->withLoad(mode: ManagedValue::MODE_PASSIVE)
        ;

        $managedValue
            ->{'with' . ucfirst($method)}(fn () => '123', ManagedValue::MODE_DEFERRED)
        ;


        self::expectException($expectedException);

        $managedValue->initialize();
    }

    #[TestWith(['default'])]
    #[TestWith(['build'])]
    public function testInitializationWithUndefinedSourceException(string $method): void
    {
        $managedValue = new ManagedValue();


        self::expectException(LogicException::class);

        $managedValue->$method();
    }

    public function testInitializationWithNoImplicitSourcesException(): void
    {
        $managedValue = new ManagedValue();

        $managedValue
            ->withDefault('default-value', ManagedValue::MODE_PASSIVE)
            ->withBuild(fn () => 'build-callback-value', ManagedValue::MODE_PASSIVE)
            ->withLoad(fn () => 'load-callback-value', ManagedValue::MODE_PASSIVE)
        ;


        self::expectException(LogicException::class);

        $managedValue->initialize();
    }

    #[TestWith(['get'])]
    #[TestWith(['getNested', []])]
    #[TestWith(['setNested', [], 'abc'])]
    #[TestWith(['deleteNested', []])]
    public function testAccessWhenNotInitializedException(string $method, ...$params): void
    {
        $managedValue = new ManagedValue();


        self::expectException(LogicException::class);

        $managedValue->$method(...$params);
    }
    #endregion

    #region setters
    #[DataProvider('valueCases')]
    public function testHeldValueAfterSet(mixed $value): void
    {
        $managedValue = new ManagedValue();


        $managedValue->set($value);


        self::assertSame(
            $value,
            $managedValue->get(),
        );
    }

    public function testHeldValueAfterSequentialSet(): void
    {
        $nestedStoreSpy = Mockery::spy(NestedStoreInterface::class);

        $repository = new Repository(
            $nestedStoreSpy,
        );

        $managedValue = $repository->create('test-managedValue');

        $managedValue
            ->withSave(mode: ManagedValue::MODE_IMMEDIATE)
        ;


        $managedValue->set('set-value-1');

        self::assertSame(
            'set-value-1',
            $managedValue->get(),
        );

        $nestedStoreSpy
            ->shouldHaveReceived('set')
            ->once()
        ;


        $nestedStoreSpy
            ->shouldReceive('set')
            ->once()
        ;

        $managedValue->set('set-value-2');

        self::assertSame(
            'set-value-2',
            $managedValue->get(),
        );
    }

    public function testSetWithIncompatibleTypeException(): void
    {
        $managedValue = new ManagedValue();

        $managedValue
            ->withDefault(123)
        ;


        self::expectException(RuntimeException::class);

        $managedValue->set('123');
    }

    /**
     * @see \MyBB\Tests\Unit\Utilities\ArraysTest
     */
    public function testValueAfterNestedModification(): void
    {
        $managedValue = new ManagedValue();

        $initialValue = [
            'a' => [
                'a.1' => [
                    'a.1.1' => 'abc',
                    'a.1.2' => 'def',
                ],
            ],
        ];

        $managedValue->set($initialValue);

        self::assertSame(
            $initialValue,
            $managedValue->get(),
        );


        $managedValue->setNested(['a', 'a.1', 'a.1.2'], 'DEF');

        self::assertSame(
            [
                'a' => [
                    'a.1' => [
                        'a.1.1' => 'abc',
                        'a.1.2' => 'DEF',
                    ],
                ],
            ],
            $managedValue->get(),
        );


        self::assertSame(
            'DEF',
            $managedValue->getNested(['a', 'a.1', 'a.1.2']),
        );


        $managedValue->deleteNested(['a', 'a.1', 'a.1.2']);

        self::assertSame(
            [
                'a' => [
                    'a.1' => [
                        'a.1.1' => 'abc',
                    ],
                ],
            ],
            $managedValue->get(),
        );
    }

    #[TestWith([ 'set', ['a' => []] ])]
    #[TestWith([ 'setNested', [], ['a' => []] ])]
    #[TestWith([ 'deleteNested', ['a', 'b'] ])]
    public function testSettersNotEffectiveWhenDisabled(string $method, ...$params): void
    {
        $managedValue = new ManagedValue();

        $managedValue
            ->withDefault([
                'a' => [
                    'b' => [],
                ],
            ])
        ;


        $managedValue
            ->withSettersEnabled(false)
        ;

        $managedValue->$method(...$params);

        self::assertSame(
            [
                'a' => [
                    'b' => [],
                ],
            ],
            $managedValue->get(),
        );


        $managedValue
            ->withSettersEnabled(true)
        ;

        $managedValue->$method(...$params);

        self::assertSame(
            [
                'a' => [],
            ],
            $managedValue->get(),
        );
    }
    #endregion

    #region storage
    public function testDeferredSaveExecutes(): void
    {
        $nestedStoreSpy = Mockery::spy(NestedStoreInterface::class);

        $repository = new Repository(
            $nestedStoreSpy,
        );

        $managedValue = $repository->create('test-managedValue');

        $managedValue->set('set-value-1');


        $nestedStoreSpy
            ->shouldNotHaveReceived('set')
        ;

        unset($repository);
        unset($managedValue);

        $nestedStoreSpy
            ->shouldHaveReceived('set')
            ->once()
        ;
    }

    public function testCommitSavesValue(): void
    {
        $nestedStoreSpy = Mockery::spy(NestedStoreInterface::class);

        $repository = new Repository(
            $nestedStoreSpy,
        );

        $managedValue = $repository->create('test-managedValue');

        $managedValue->set('set-value-1');


        $nestedStoreSpy
            ->shouldNotHaveReceived('set')
        ;

        $managedValue->commit();

        $nestedStoreSpy
            ->shouldHaveReceived('set')
            ->once()
        ;
    }

    public function testSaveWithNoModificationsExecutes(): void
    {
        $nestedStoreSpy = Mockery::spy(NestedStoreInterface::class);

        $repository = new Repository(
            $nestedStoreSpy,
        );

        $managedValue = $repository->create('test-managedValue');


        $nestedStoreSpy
            ->shouldReceive('get')

                ->with(['test-managedValue'])
                ->andReturn([
                    'stamp' => null,
                    'value' => 'stored-value',
                ])
                ->once()
            ->shouldReceive('set')
                ->with(
                    ['test-managedValue'],
                    [
                        'stamp' => null,
                        'value' => 'stored-value',
                    ],
                )
                ->once()
        ;

        $managedValue->save();
    }

    #[TestWith([ ['value' => 'abc'] ])]
    #[TestWith([ ['stamp' => null, 'value' => '123'] ])]
    public function testLoadFromInvalidDataNotEffective(array $storedValue): void
    {
        $nestedStoreStub = Mockery::mock(NestedStoreInterface::class)
            ->shouldReceive('get')
                ->with(['test-managedValue'])
                ->andReturn($storedValue)
            ->getMock()
        ;

        $repository = new Repository(
            $nestedStoreStub,
        );


        $managedValue = $repository->create('test-managedValue');

        $managedValue
            ->withDefault(123, ManagedValue::MODE_PASSIVE)
        ;


        self::expectException(LogicException::class);

        $managedValue->initialize();
    }

    public function testStoreMethodsWithNoSetStore(): void
    {
        $managedValue = new ManagedValue();

        $managedValue->set('abc');


        self::assertSame(
            false,
            $managedValue->save(),
        );
        self::assertSame(
            false,
            $managedValue->commit(),
        );
        self::assertSame(
            false,
            $managedValue->load(),
        );
    }

    public function testInstantiationWithStoreWithoutPathException(): void
    {
        self::expectException(LogicException::class);

        new ManagedValue(
            Mockery::mock(NestedStoreInterface::class),
        );
    }

    #endregion

    #region stamps
    public function testStampValidReportsSuccess(): void
    {
        $managedValue = new ManagedValue();

        $managedValue
            ->withStampValidation(fn (string $stamp) => $stamp === 'valid-stamp')
        ;

        $managedValue->set('abc', 'valid-stamp');


        self::assertSame(
            true,
            $managedValue->stampValid(),
        );
    }

    public function testStampValidReportsFailure(): void
    {
        $managedValue = new ManagedValue();

        $managedValue
            ->withStampValidation(fn (string $stamp) => $stamp === 'valid-stamp')
        ;

        $managedValue->set('abc', 'invalid-stamp');


        self::assertSame(
            false,
            $managedValue->stampValid(),
        );
    }

    public function testStampValidWithNullStampReportsFailure(): void
    {
        $managedValue = new ManagedValue();

        $managedValue
            ->withStampValidation(fn (string $stamp) => $stamp === 'valid-stamp')
        ;

        $managedValue->set('abc', null);


        self::assertSame(
            false,
            $managedValue->stampValid(),
        );
    }

    public function testStampValidInitializes(): void
    {
        $managedValue = new ManagedValue();

        $managedValue
            ->withBuild(
                function (&$stamp) {
                    $stamp = 'valid-stamp';

                    return 'build-callback-value';
                }
            )
            ->withStampValidation(fn (string $stamp) => $stamp === 'valid-stamp')
        ;

        self::assertSame(
            false,
            $managedValue->initialized(),
        );


        $result = $managedValue->stampValid();

        self::assertSame(true, $result);

        self::assertSame(
            true,
            $managedValue->initialized(),
        );
    }

    public function testLoadFromInvalidStampNotEffective(): void
    {
        $nestedStoreStub = Mockery::mock(NestedStoreInterface::class)
            ->shouldReceive('get')
                ->with(['test-managedValue'])
                ->andReturn([
                    'stamp' => 'stored-stamp',
                    'value' => 'stored-value',
                ])
                ->once()
            ->getMock()
        ;

        $repository = new Repository(
            $nestedStoreStub,
        );

        $managedValue = $repository->create('test-managedValue');

        $managedValue
            ->withStampValidation(fn (string $stamp) => $stamp === 'valid-stamp')
        ;

        self::assertSame(
            false,
            $managedValue->initialized(),
        );


        self::expectException(LogicException::class);

        $managedValue->initialize();
    }

    public function testInitializationWithInvalidStampTriggersRebuild(): void
    {
        $nestedStoreMock = Mockery::mock(NestedStoreInterface::class)
            ->shouldIgnoreMissing()
            ->shouldReceive('get')
                ->with(['test-managedValue'])
                ->andReturn([
                    'stamp' => 'stored-stamp',
                    'value' => 'stored-value',
                ])
                ->once()
            ->getMock()
        ;

        $repository = new Repository(
            $nestedStoreMock,
        );

        $managedValue = $repository->create('test-managedValue');

        $managedValue
            ->withBuild(
                function (&$stamp) {
                    $stamp = 'build-callback-stamp';

                    return 'build-callback-value';
                }
            )
            ->withSave(mode: ManagedValue::MODE_IMMEDIATE)
            ->withStampValidation(fn (string $stamp) => $stamp === 'valid-stamp')
        ;

        self::assertSame(
            false,
            $managedValue->initialized(),
        );


        $nestedStoreMock
            ->shouldReceive('set')
                ->with(
                    ['test-managedValue'],
                    [
                        'stamp' => 'build-callback-stamp',
                        'value' => 'build-callback-value',
                    ],
                )
                ->once()
        ;

        $managedValue->initialize();

        self::assertSame(
            'build-callback-value',
            $managedValue->get(),
        );
    }

    #[TestWith(['set', ['a' => 'abc'], 'external-stamp'])]
    #[TestWith(['setNested', ['a'], 'abc', 'external-stamp'])]
    #[TestWith(['deleteNested', ['x'], 'external-stamp'])]
    public function testModificationWithStampApplies(string $method, ...$params): void
    {
        $nestedStoreSpy = Mockery::spy(NestedStoreInterface::class);

        $repository = new Repository(
            $nestedStoreSpy,
        );

        $managedValue = $repository->create('test-managedValue');

        $managedValue
            ->withSave(mode: ManagedValue::MODE_IMMEDIATE)
        ;

        $managedValue->set([
            'a' => 'abc',
        ]);


        $nestedStoreSpy
            ->shouldReceive('set')
                ->once()
                ->with(
                    ['test-managedValue'],
                    [
                        'stamp' => 'external-stamp',
                        'value' => [
                            'a' => 'abc',
                        ],
                    ],
                )
        ;

        $managedValue->$method(...$params);
    }

    public function testStampValidWithNoSetCallbackException(): void
    {
        $managedValue = new ManagedValue();

        $managedValue->set('abc');


        self::expectException(LogicException::class);

        $managedValue->stampValid();
    }
    #endregion

    #region clearing
    public function testClear(): void
    {
        $nestedStoreSpy = Mockery::spy(NestedStoreInterface::class);

        $repository = new Repository(
            $nestedStoreSpy
        );

        $managedValue = $repository->create('test-managedValue');

        $managedValue
            ->withStampValidation(fn () => true)
        ;

        $managedValue->set('test-value');

        self::assertSame(
            'test-value',
            $managedValue->get(),
        );


        $nestedStoreSpy
            ->shouldReceive('delete')
            ->with(['test-managedValue'])
            ->once()
        ;

        $managedValue->clear();

        self::assertSame(
            false,
            $managedValue->initialized(),
        );
    }

    public function testClearUninitialized(): void
    {
        $managedValue = new ManagedValue();

        self::assertSame(
            false,
            $managedValue->initialized(),
        );


        $managedValue->clear();

        self::assertSame(
            false,
            $managedValue->initialized(),
        );
    }
    #endregion
}
