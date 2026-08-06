<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Path;

/**
 * Resolves paths to concrete Repositories and Extensions.
 */
final readonly class RepositoryRegistry
{
    public function __construct(
        /**
         * @var array<class-string<Extension>, Repository>
         */
        private array $repositories,
    ) {}


    /**
     * Returns the Extension Repository for the given Extension type class.
     *
     * @param class-string<Extension> $class
     */
    public function getRepositoryForExtensionClass(string $class): Repository
    {
        return
            $this->repositories[$class]
            ?? throw new InvalidArgumentException('No Extension Repository for class `' . $class . '`');
    }

    /**
     * Returns the Extension Repository relevant to the given exact or base path.
     */
    public function getRepositoryFromBasePath(string $path): Repository
    {
        return $this->getRepositoryFromAbsoluteBasePath(
            Path::makeAbsolute($path, MYBB_ROOT)
        );
    }

    /**
     * Returns the Extension Repository relevant to the given absolute exact or base path.
     */
    public function getRepositoryFromAbsoluteBasePath(string $path): Repository
    {
        foreach ($this->repositories as $repository) {
            if (Path::isBasePath($repository::getAbsoluteBasePath(), $path)) {
                return $repository;
            }
        }

        throw new InvalidArgumentException('No matching Extension Repository for path `' . $path . '`');
    }


    /**
     * Returns object representation of the given Extension package, or null if it doesn't exist.
     *
     * @note The Extension type is usually known by calling context instead.
     *
     * @throws Exception if a matching Extension is found in multiple Repositories.
     */
    public function getExistingExtensionFromPackageName(string $packageName): ?Extension
    {
        $result = null;

        foreach ($this->repositories as $repository) {
            $extension = $repository->getExisting($packageName);

            if ($extension !== null) {
                if ($result === null) {
                    $result = $extension;
                } else {
                    throw new Exception('Extension with package name `' . $packageName . '` exists for more than one type (' . $result::class . ', ' . $extension::class . ')');
                }
            }
        }

        return $result;
    }

    /**
     * Returns the Extension with the given exact or base path.
     */
    public function getExtensionFromPath(string $path): Extension
    {
        return $this->getExtensionFromAbsolutePath(
            Path::makeAbsolute($path, MYBB_ROOT)
        );
    }

    /**
     * Returns the Extension with the given exact or base absolute path.
     */
    public function getExtensionFromAbsolutePath(string $path): Extension
    {
        $repository = $this->getRepositoryFromAbsoluteBasePath($path);

        $repositoryRelativePath = Path::makeRelative($path, $repository::getAbsoluteBasePath());

        $packageName = explode('/', Path::canonicalize($repositoryRelativePath))[0];

        if (($packageName === '' || $packageName === '.')) {
            throw new InvalidArgumentException('No Extension identified by path `' . $path . '`');
        }

        return $repository->get($packageName);
    }
}
