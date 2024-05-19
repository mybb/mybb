<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use MyBB\View\NamespaceType;
use MyBB\View\Themelet\Themelet;

interface ViewExtensionInterface
{
    /**
     * @var NamespaceType[]
     */
    public const NAMESPACE_TYPE_ACCESS = [];

    /**
     * Resources are located directly in the Themelet directory,
     * and assigned to an implied namespace.
     *
     * @see self::getThemeletDirectNamespace()
     */
    public const THEMELET_DIRECT_NAMESPACE = false;

    public function getThemelet(): Themelet;
    public function getThemeletAbsolutePath(): string;
    public function getThemeletDirectNamespace(): string;
}
