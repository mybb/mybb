<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use BadMethodCallException;
use MyBB\View\Themelet\Themelet;

trait ViewExtensionTrait
{
    private Themelet $themelet;

    public function getThemelet(): Themelet
    {
        return $this->themelet ??= Themelet::fromExtension($this);
    }

    public function getThemeletAbsolutePath(): string
    {
        return $this->getAbsolutePath() . static::PACKAGE_RELATIVE_THEMELET_PATH;
    }

    public function getThemeletDirectNamespace(): string
    {
        throw new BadMethodCallException('Cannot use direct namespace with Extension type `' . static::class . '`');
    }
}
