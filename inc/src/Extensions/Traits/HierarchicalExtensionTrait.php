<?php

declare(strict_types=1);

namespace MyBB\Extensions\Traits;

use Exception;
use Illuminate\Support\Arr;
use MyBB\Extensions\Contracts\HierarchicalExtensionInterface;
use MyBB\Extensions\Repository;

/**
 * Resolves Extension inheritance.
 */
trait HierarchicalExtensionTrait
{
    private array $inheritanceChain;
    private array $descendants;
    private array $extensionsDeclaringAsAncestor;

    /**
     * Returns nodes sorted topologically using Kahn's algorithm.
     *
     * @template T scalar
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
     */
    public function getInheritanceChain(Repository $repository): array
    {
        if (!isset($this->inheritanceChain)) {
            $orderedNames = self::getLinearizedNodes(
                $this->getInheritanceChainDirectDependants($repository)
            );

            if ($orderedNames === null) {
                throw new Exception('Circular inheritance declared involving Extension `' . $this->getPackageName() . '`');
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
     */
    public function getAncestors(Repository $repository): array
    {
        return Arr::except(
            $this->getInheritanceChain($repository),
            $this->getPackageName(),
        );
    }

    /**
     * @param Repository<HierarchicalExtensionInterface> $repository
     * @param string[] $dependants
     * @return array<string, HierarchicalExtensionInterface>
     */
    public function getDescendants(Repository $repository, array $dependants = []): array
    {
        if (!isset($this->descendants)) {
            $extensions = [];

            if (in_array($this->getPackageName(), $dependants)) {
                throw new Exception('Circular inheritance declared involving Extension `' . $this->getPackageName() . '`');
            }

            $dependants[] = $this->getPackageName();

            foreach ($this->getExtensionsDeclaringAsAncestor($repository) as $packageName => $extension) {
                $extensions[$packageName] = $extension;

                $extensions = array_merge(
                    $extensions,
                    $extension->getDescendants($repository, $dependants),
                );
            }

            $this->descendants = $extensions;
        }

        return $this->descendants;
    }

    /**
     * Returns Extensions explicitly declaring the Extension as their ancestor.
     *
     * @param Repository<HierarchicalExtensionInterface> $repository
     * @return array<string, HierarchicalExtensionInterface>
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
            foreach ($manifest['extra']['inherits'] as $key => $value) {
                if (!is_string($value)) {
                    throw new Exception('Invalid ancestor declaration for `' . $this->getPackageName() . '`');
                }

                if (is_int($key)) {
                    $packageName = $value;
                    $versionDeclaration = null;
                } elseif (is_string($key)) {
                    $packageName = $key;
                    $versionDeclaration = $value;
                } else {
                    throw new Exception('Invalid ancestor declaration for `' . $this->getPackageName() . '`');
                }

                if (array_key_exists($packageName, $declarations)) {
                    throw new Exception('Duplicate inheritance declared involving Extension `' . $packageName . '`');
                }

                $declarations[$packageName] = $versionDeclaration;
            }
        }

        return $declarations;
    }

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
     */
    private function getInheritanceChainDirectDependants(Repository $repository, array $visited = []): array
    {
        $dependants = [];

        $dependants[$this->getPackageName()] = [];

        foreach ($this->getAncestorDeclarations() as $packageName => $versionDeclaration) {
            $extension = $repository->get($packageName);

            if (!$extension->canInheritFrom($this)) {
                throw new Exception('Illegal inheritance declared involving Extension `' . $packageName . '`');
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
