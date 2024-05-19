<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Utilities\Fixtures\Component;

class Component implements ComponentInterface
{
    public function undecoratedMethod(): string
    {
        return __CLASS__;
    }

    public function decoratedMethod(): string
    {
        return __CLASS__;
    }

    public function cascadeMethod(): string
    {
        return __CLASS__;
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
