<?php

declare(strict_types=1);

namespace MyBB\Extensions\Traits;

use BadMethodCallException;
use MyBB\View\Themelet\Themelet;

trait ViewExtensionTrait
{
    /**
     * The Extension's Themelet.
     */
    private Themelet $themelet;

    /**
     * Returns the Extension's Themelet.
     */
    public function getThemelet(): Themelet
    {
        return $this->themelet ??= Themelet::fromExtension($this);
    }

    /**
     * Returns the absolute path to the Extension's Themelet.
     */
    public function getThemeletAbsolutePath(): string
    {
        return $this->getAbsolutePath() . static::PACKAGE_RELATIVE_THEMELET_PATH;
    }

    /**
     * Returns the name of the implied namespace.
     */
    public function getThemeletDirectNamespace(): string
    {
        throw new BadMethodCallException('Cannot use direct namespace with Extension type `' . static::class . '`');
    }
}
