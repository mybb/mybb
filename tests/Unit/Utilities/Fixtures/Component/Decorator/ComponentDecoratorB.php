<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Utilities\Fixtures\Component\Decorator;

use MyBB\Tests\Unit\Utilities\Fixtures\Component\ComponentInterface;

class ComponentDecoratorB extends ComponentDecorator implements ComponentInterface
{
    private string $option;

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

    public function addedMethodB(): string
    {
        return __CLASS__;
    }

    public function getDecoratedObject(): object
    {
        return $this->getDecorated();
    }

    public function optionDependentMethod(): string
    {
        return $this->option;
    }

    public function setOption(string $value): void
    {
        $this->option = $value;
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
