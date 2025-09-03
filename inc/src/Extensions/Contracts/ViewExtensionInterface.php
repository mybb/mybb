<?php

declare(strict_types=1);

namespace MyBB\Extensions\Contracts;

use MyBB\View\NamespaceType;
use MyBB\View\Themelet\Themelet;

/**
 * An Extension that may supply its own Themelet.
 */
interface ViewExtensionInterface
{
    /**
     * The types of namespaces the Extension's Themelet can contain.
     *
     * @var NamespaceType[]
     */
    public const NAMESPACE_TYPE_ACCESS = [];

    /**
     * Whether Resources are located directly in the Themelet directory,
     * and assigned to an implied namespace.
     *
     * @see self::getThemeletDirectNamespace()
     */
    public const THEMELET_DIRECT_NAMESPACE = false;

    public function getThemelet(): Themelet;
    public function getThemeletAbsolutePath(): string;
    public function getThemeletDirectNamespace(): string;
}
