<?php

declare(strict_types=1);

namespace MyBB\Extensions\Traits;

use BadMethodCallException;
use MyBB\View\Viewlet\Viewlet;

trait ViewExtensionTrait
{
    /**
     * The Extension's Viewlet.
     */
    private Viewlet $viewlet;

    /**
     * Returns the Extension's Viewlet.
     */
    public function getViewlet(): Viewlet
    {
        return $this->viewlet ??= Viewlet::fromExtension($this);
    }

    /**
     * Returns the absolute path to the Extension's Viewlet.
     */
    public function getViewletAbsolutePath(): string
    {
        return $this->getAbsolutePath() . static::PACKAGE_RELATIVE_VIEWLET_PATH;
    }

    /**
     * Returns the name of the implied namespace.
     */
    public function getViewletDirectNamespace(): string
    {
        throw new BadMethodCallException('Cannot use direct namespace with Extension type `' . static::class . '`');
    }
}
