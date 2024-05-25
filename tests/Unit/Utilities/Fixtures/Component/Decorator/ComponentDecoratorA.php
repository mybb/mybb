<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Utilities\Fixtures\Component\Decorator;

use MyBB\Tests\Unit\Utilities\Fixtures\Component\ComponentInterface;

class ComponentDecoratorA extends ComponentDecorator implements ComponentInterface
{
    public function decoratedMethod(): string
    {
        return __CLASS__;
    }

    public function immediateDecoratedMethod(): string
    {
        return $this->getDecorated()->decoratedMethod();
    }

    public function cascadeMethod(): string
    {
        return $this->getDecorated()->cascadeMethod();
    }

    public function addedCommonMethod(): string
    {
        return __CLASS__;
    }

    public function addedMethodA(): string
    {
        return __CLASS__;
    }

    public function noopMethod(): void
    {
        throw self::decoratedCallException();
    }

    public function getDecoratedObject(): object
    {
        return $this->getDecorated();
    }

    protected function protectedMethod(): string
    {
        return __CLASS__;
    }

    private function privateMethod(): string
    {
        return __CLASS__;
    }
}
