<?php

declare(strict_types=1);

namespace MyBB\View\Themelet;

use MyBB\Extensions\ViewExtensionInterface;
use MyBB\Utilities\ManagedValue\Repository as ManagedValueRepository;
use MyBB\View\Asset\Asset;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\NamespaceType;
use MyBB\View\Resource;
use MyBB\View\ResourceType;
use MyBB\View\Themelet\NamespaceCargo\Repository;

/**
 * @method static self fromExtension(?ViewExtensionInterface $extension = null)
 * @method ViewExtensionInterface|null getExtension()
 * @method string getIdentifier()
 * @method string getAbsolutePath()
 * @method ManagedValueRepository getManagedValueRepository()
 *
 * // AssetsTrait
 * @method Repository getAssetRepository(string $namespace)
 * @method Asset getAsset(Locator $locator, ?string $declarationNamespace = null)
 * @method array getAssetPropertiesOfType(string $namespace, ResourceType $type)
 * @method array getAssetProperties(string $namespace)
 *
 * // NamespacesTrait
 * @method bool hasNamespaceTypeAccess(NamespaceType $type)
 * @method array getNamespaces()
 * @method array getNamespaceAbsolutePaths()
 * @method string getNamespaceAbsolutePath(string $namespace)
 * @method string getNamespaceDirectoryPath(string $namespace)
 * @method string getNamespaceFromDirectoryName(string $name)
 *
 * // ResourcesTrait
 * @method Repository getResourceRepository(string $namespace)
 * @method string getResourceTypeAbsolutePath(string $namespace, ResourceType $type)
 * @method array getResourceTypeAbsolutePaths(ResourceType $type)
 * @method array getResources(?array $namespaces = null, ?array $resourceTypes = null)
 * @method array getNamespaceResources(string $namespace, ?array $resourceTypes = null)
 * @method bool hasResource(ThemeletLocator $locator)
 * @method Resource|null getExistingResource(ThemeletLocator $locator)
 * @method Resource|null getResource(ThemeletLocator $locator)
 * @method Resource|null createResource(ThemeletLocator $locator)
 * @method array getResourceProperties()
 */
interface ThemeletInterface {}
