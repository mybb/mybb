<?php

declare(strict_types=1);

namespace MyBB\Extensions\Contracts;

use MyBB\View\Viewlet\NamespaceType;
use MyBB\View\Viewlet\Viewlet;

/**
 * An Extension that may supply its own Viewlet.
 */
interface ViewExtensionInterface
{
    /**
     * The types of namespaces the Extension's Viewlet can contain.
     *
     * @var NamespaceType[]
     */
    public const NAMESPACE_TYPE_ACCESS = [];

    /**
     * Whether Resources are located directly in the Viewlet directory,
     * and assigned to an implied namespace.
     *
     * @see self::getViewletDirectNamespace()
     */
    public const VIEWLET_DIRECT_NAMESPACE = false;

    public function getViewlet(): Viewlet;
    public function getViewletAbsolutePath(): string;
    public function getViewletDirectNamespace(): string;
}
