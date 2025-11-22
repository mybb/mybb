<?php

declare(strict_types=1);

namespace MyBB\Extensions\Traits;

use Illuminate\Support\Arr;
use MyBB\Extensions\Contracts\HierarchicalExtensionInterface;
use MyBB\Extensions\Exception as ExtensionException;
use MyBB\Extensions\Repository;

/**
 * Resolves Extension inheritance.
 */
trait HierarchicalExtensionTrait
{
    /**
     * The Extension and its ancestors from closest to furthest.
     *
     * @var array<string, HierarchicalExtensionInterface>
     */
    private array $inheritanceChain;

    /**
     * Descendants of the Extension.
     *
     * @var array<string, HierarchicalExtensionInterface>
     */
    private array $descendants;

    /**
     * Extensions explicitly declaring the Extension as their ancestor.
     *
     * @var array<string, HierarchicalExtensionInterface>
     */
    private array $extensionsDeclaringAsAncestor;

    /**
     * Returns nodes sorted topologically using Kahn's algorithm.
     *
     * @template T of scalar
     * @param array<T, T[]> $nodes Nodes as key, and source nodes of their incoming edges.
     * @return ?list<T>
     */
    private static function getLinearizedNodes(array $nodes): ?array
    {
        $linearized = [];

        while ($nodes !== []) {
            $headNodesFound = false;

            foreach ($nodes as $targetNode => $sourceNodes) {
                if ($sourceNodes === []) {
                    $headNodesFound = true;

                    $linearized[] = $targetNode;

                    unset($nodes[$targetNode]);

                    foreach ($nodes as &$sources) {
                        unset($sources[array_search($targetNode, $sources)]);
                    }
                }
            }

            if (!$headNodesFound) {
                return null;
            }
        }

        return $linearized;
    }

    /**
     * Returns the Extension and its ancestors from closest to furthest.
     *
     * @param Repository<HierarchicalExtensionInterface> $repository
     * @return array<string, HierarchicalExtensionInterface>
     *
     * @throws ExtensionException if the declared inheritance is invalid.
     */
    public function getInheritanceChain(Repository $repository): array
    {
        if (!isset($this->inheritanceChain)) {
            $orderedNames = self::getLinearizedNodes(
                $this->getInheritanceChainDirectDependants($repository)
            );

            if ($orderedNames === null) {
                throw new ExtensionException(
                    'Circular inheritance declared involving Extension `' . $this->getPackageName() . '`',
                    $this,
                );
            }

            $this->inheritanceChain = array_combine(
                $orderedNames,
                array_map(
                    fn (string $packageName) => $repository->get($packageName),
                    $orderedNames,
                ),
            );
        }

        return $this->inheritanceChain;
    }

    /**
     * Returns ancestors from closest to furthest.
     *
     * @param Repository<HierarchicalExtensionInterface> $repository
     * @return array<string, HierarchicalExtensionInterface>
     *
     * @throws ExtensionException if the declared inheritance is invalid.
     */
    public function getAncestors(Repository $repository): array
    {
        return Arr::except(
            $this->getInheritanceChain($repository),
            $this->getPackageName(),
        );
    }

    /**
     * Returns descendants of the Extension.
     *
     * @param Repository<HierarchicalExtensionInterface> $repository
     * @return array<string, HierarchicalExtensionInterface>
     *
     * @throws ExtensionException if the declared inheritance is invalid.
     */
    public function getDescendants(Repository $repository): array
    {
        return $this->getDescendantsRecursively($repository);
    }

    /**
     * Returns Extensions explicitly declaring the Extension as their ancestor.
     *
     * @param Repository<HierarchicalExtensionInterface> $repository
     * @return array<string, HierarchicalExtensionInterface>
     *
     * @throws ExtensionException if the declared inheritance is invalid.
     */
    public function getExtensionsDeclaringAsAncestor(Repository $repository): array
    {
        if (!isset($this->extensionsDeclaringAsAncestor)) {
            $extensions = [];

            foreach ($repository->getAll() as $packageName => $extension) {
                if (
                    array_key_exists(
                        $this->getPackageName(),
                        $extension->getAncestorDeclarations(),
                    )
                ) {
                    $extensions[$packageName] = $extension;
                }
            }

            $this->extensionsDeclaringAsAncestor = $extensions;
        }

        return $this->extensionsDeclaringAsAncestor;
    }

    /**
     * Returns declared package names as keys, and version strings as values.
     *
     * @return array<string, ?string>
     *
     * @throws ExtensionException if the declared inheritance is invalid.
     */
    private function getAncestorDeclarations(): array
    {
        $declarations = [];

        $manifest = $this->getManifest();

        if (
            $manifest !== null &&
            isset($manifest['extra']['inherits']) &&
            is_array($manifest['extra']['inherits'])
        ) {
            $isList = array_is_list($manifest['extra']['inherits']);

            foreach ($manifest['extra']['inherits'] as $key => $value) {
                if (!is_string($value)) {
                    throw new ExtensionException('Invalid ancestor declaration', $this);
                }

                if (is_int($key) && $isList) {
                    $packageName = $value;
                    $versionDeclaration = null;
                } elseif (is_string($key)) {
                    $packageName = $key;
                    $versionDeclaration = $value;
                } else {
                    throw new ExtensionException('Invalid ancestor declaration', $this);
                }

                if (array_key_exists($packageName, $declarations)) {
                    throw new ExtensionException(
                        'Duplicate inheritance declared involving Extension `' . $packageName . '`',
                        $this,
                    );
                }

                $declarations[$packageName] = $versionDeclaration;
            }
        }

        return $declarations;
    }

    /**
     * Returns descendants of the Extension.
     *
     * @param Repository<HierarchicalExtensionInterface> $repository
     * @param string[] $dependants
     * @return array<string, HierarchicalExtensionInterface>
     *
     * @throws ExtensionException if the declared inheritance is invalid.
     */
    private function getDescendantsRecursively(Repository $repository, array $dependants = []): array
    {
        if (!isset($this->descendants)) {
            $extensions = [];

            if (in_array($this->getPackageName(), $dependants)) {
                throw new ExtensionException(
                    'Circular inheritance declared involving Extension `' . $this->getPackageName() . '`',
                    $this,
                );
            }

            $dependants[] = $this->getPackageName();

            foreach ($this->getExtensionsDeclaringAsAncestor($repository) as $packageName => $extension) {
                $extensions[$packageName] = $extension;

                $extensions = array_merge(
                    $extensions,
                    $extension->getDescendantsRecursively($repository, $dependants),
                );
            }

            $this->descendants = $extensions;
        }

        return $this->descendants;
    }

    /**
     * Whether the given Extension can be an ancestor of this Extension.
     */
    private function canInheritFrom(self $extension): bool
    {
        return true;
    }

    /**
     * Returns Extensions directly reliant on each Extension in the inheritance chain,
     * represented by package name (an inverse mapping of immediate dependencies).
     *
     * Used to construct the final, reconciled inheritance chain.
     *
     * @param Repository<HierarchicalExtensionInterface> $repository
     * @return array<string, string[]>
     *
     * @throws ExtensionException if the declared inheritance is invalid.
     */
    private function getInheritanceChainDirectDependants(Repository $repository, array $visited = []): array
    {
        $dependants = [];

        $dependants[$this->getPackageName()] = [];

        foreach ($this->getAncestorDeclarations() as $packageName => $versionDeclaration) {
            $extension = $repository->get($packageName);

            if (!$extension->exists()) {
                throw new ExtensionException(
                    'Missing ancestor Extension `' . $packageName . '`',
                    $this,
                );
            }

            if (!$this->canInheritFrom($extension)) {
                throw new ExtensionException(
                    'Illegal inheritance declared involving Extension `' . $packageName . '`',
                    $this,
                );
            }

            if (!in_array($packageName, $visited)) {
                $dependants[$packageName][] = $this->getPackageName();

                $visited[] = $packageName;

                $dependants = array_merge_recursive(
                    $dependants,
                    $extension->getInheritanceChainDirectDependants($repository, $visited),
                );
            }
        }

        return $dependants;
    }
}
