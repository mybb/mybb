<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\NamespaceCargo\Resource;

use FilesystemIterator;
use LogicException;
use MyBB\Cargo\FileRepositoryInterface;
use MyBB\Cargo\RepositoryInterface;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Resource;
use MyBB\View\ResourceType;
use MyBB\View\Themelet\ThemeletInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Path;

/**
 * Manages Resources and their declarations in a Themelet's namespace.
 */
class Repository extends \MyBB\View\Themelet\NamespaceCargo\Repository implements FileRepositoryInterface
{
    public const NAME = 'resources';

    public function getRepositoryInThemelet(ThemeletInterface $themelet): RepositoryInterface
    {
        return $themelet->getResourceRepository($this->namespace);
    }

    /**
     * @param ?ResourceType[] $resourceTypes
     * @return array<string, Resource>
     */
    public function getAll(?array $resourceTypes = null): array
    {
        $results = [];

        foreach ($resourceTypes ?? ResourceType::cases() as $resourceType) {
            $resourceTypeAbsolutePath = $this->themelet->getResourceTypeAbsolutePath(
                $this->namespace,
                $resourceType,
            );

            if (is_dir($resourceTypeAbsolutePath)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $resourceTypeAbsolutePath,
                        FilesystemIterator::SKIP_DOTS,
                    )
                );

                foreach ($files as $file) {
                    $path = Path::makeRelative($file->getRealpath(), $resourceTypeAbsolutePath);

                    $locator = ThemeletLocator::fromString(
                        $path,
                        [
                            'namespace' => ThemeletLocator::COMPONENT_UNSET,
                            'type' => ThemeletLocator::COMPONENT_UNSET,
                        ],
                        [
                            'namespace' => $this->namespace,
                            'type' => $resourceType,
                        ],
                    );

                    $key = $locator->getNamespaceRelativeIdentifier();

                    $results[$key] = $this->getExisting($locator);
                }
            }
        }

        return $results;
    }

    public function has(string|ThemeletLocator $key): bool
    {
        return $this->get($key)->exists();
    }

    public function getExisting(string|ThemeletLocator $key): ?Resource
    {
        $resource = $this->get($key);

        if ($resource->exists()) {
            return $resource;
        } else {
            return null;
        }
    }

    public function get(string|ThemeletLocator $key): Resource
    {
        if (!($key instanceof ThemeletLocator)) {
            $key = ThemeletLocator::fromNamespaceRelativeIdentifier($this->namespace, $key);
        }

        return new Resource($this->themelet, $key);
    }

    public function create(string|ThemeletLocator $key): Resource
    {
        $resource = $this->get($key);

        if ($resource->exists()) {
            throw new LogicException('Resource `' . $key->getString() . '` already exists');
        } else {
            return $resource;
        }
    }
}
