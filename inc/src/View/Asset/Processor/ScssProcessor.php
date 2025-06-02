<?php

declare(strict_types=1);

namespace MyBB\View\Asset\Processor;

use Exception;
use MyBB\View\HierarchicalResource;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Resource;
use MyBB\View\Themelet\Themelet;
use MyBB\View\Themelet\ThemeletInterface;
use ScssPhp\ScssPhp\Compiler;
use Symfony\Component\Filesystem\Path;
use UnexpectedValueException;

/**
 * Converts Sass/SCSS to CSS.
 */
class ScssProcessor extends Processor
{
    /**
     * A cache of used, non-resolved Themelets.
     *
     * @see self::getResourceFromAbsolutePath()
     *
     * @var ThemeletInterface[]
     */
    protected array $sourceThemelets = [];

    /**
     * Whether to use a temporary directory with resolved Resource files.
     *
     * As ScssPhp prioritizes relative paths over provided import paths/functions,
     * the files are copied - according to inheritance - into a single location first when necessary.
     */
    private bool $useResolvedDirectory = false;

    /**
     * A mapping of absolute paths to importable Resources.
     *
     * @see self::$useResolvedDirectory
     *
     * @var array<string, Resource>
     */
    private array $importableResourceFiles = [];

    public function getOutputContent(): string
    {
        $importableResources = $this->getImportableResources();

        $resolvedThemeletIdentifiers = $this->getResolvedThemeletIdentifiers($importableResources);

        if (count($resolvedThemeletIdentifiers) > 1) {
            $this->useResolvedDirectory = true;

            $this->prepareImportableResourceFiles($importableResources);

            $sourcePath = $this->getImportableResourceAbsolutePath(
                $this->asset->getResource()
            );
        } else {
            $this->sourceThemelets[] = $this->asset->getResource()->getThemelet();

            $sourcePath = $this->asset->getResource()->getAbsolutePath();
        }


        $compiler = new Compiler();

        $compiler->addImportPath(
            $this->getImportAbsolutePath(...)
        );

        $compiled = $compiler->compileString($this->inputContent, $sourcePath);

        // add using actual results instead of the callback
        $this->addSourcesFromAbsolutePaths(
            $compiled->getIncludedFiles()
        );

        return $compiled->getCss();
    }

    /**
     * Returns Resources that can be included as sources using source code declarations.
     *
     * @return array<string, Resource>
     */
    private function getImportableResources(): array
    {
        return $this->asset->getThemelet()->getResources(
            namespaces: [$this->asset->getResource()->getNamespace()],
            resourceTypes: [$this->asset->getResource()->getType()],
        );
    }

    /**
     * Returns identifiers of Themelets to which at least one given Resource resolves to.
     *
     * @param Resource[] $resources
     *
     * @return string[]
     */
    private function getResolvedThemeletIdentifiers(array $resources): array
    {
        return array_values(
            array_unique(
                array_map(
                    fn (Resource $resource) =>
                        (
                            $resource instanceof HierarchicalResource
                                ? $resource->getResolved()
                                : $resource
                        )
                            ->getThemelet()
                            ->getIdentifier(),
                    $resources,
                ),
            ),
        );
    }

    /**
     * Returns the absolute path to a Resource referenced in a source code declaration.
     *
     * @see https://scssphp.github.io/scssphp/docs/extending/importers.html
     */
    private function getImportAbsolutePath(string $path): ?string
    {
        if (Compiler::isCssImport($path)) {
            return null;
        }

        try {
            foreach ($this->getImportCandidatePaths($path) as $candidatePath) {
                $locator = $this->asset->getLocator()->getSibling($candidatePath);

                if ($this->asset->getThemelet()->hasResource($locator)) {
                    $resource = $this->asset->getThemelet()->getExistingResource($locator);

                    if (!in_array($resource->getThemelet(), $this->sourceThemelets)) {
                        $this->sourceThemelets[] = $resource->getThemelet();
                    }

                    if ($this->useResolvedDirectory) {
                        return $this->getImportableResourceAbsolutePath($resource);
                    } else {
                        return $resource->getAbsolutePath();
                    }
                }
            }
        } catch (Exception) {
        }

        return null;
    }

    /**
     * Returns the paths to check when importing files using source code declarations.
     *
     * @see Compiler::resolveImportPath()
     *
     * @return string[]
     */
    private function getImportCandidatePaths(string $path): array
    {
        $candidatePaths = [];

        $pathsWithExtension = [];

        if (preg_match('/\.s[ac]ss$/', $path)) {
            $pathsWithExtension[] = $path;
        } else {
            foreach (['sass', 'scss', 'css'] as $extension) {
                $pathsWithExtension[] = $path . '.' . $extension;
            }
        }

        foreach ($pathsWithExtension as $pathWithExtension) {
            $candidatePaths[] = dirname($pathWithExtension) . '/_' . basename($pathWithExtension);
            $candidatePaths[] = $pathWithExtension;
        }

        return $candidatePaths;
    }

    /**
     * Registers Resources as contributing to the resulting Asset using the Resources' absolute paths.
     *
     * @param string[] $paths
     *
     * @throws UnexpectedValueException If no matching Resource can be found for a given path.
     */
    private function addSourcesFromAbsolutePaths(array $paths): void
    {
        foreach ($paths as $path) {
            $path = Path::normalize($path);

            $resource =
                $this->importableResourceFiles[$path]
                ?? $this->getResourceFromAbsolutePath($path)
            ;

            if ($resource) {
                $this->publication->addSource($resource);

                continue;
            }

            throw new UnexpectedValueException('Unexpected Resource `' . $path . '` used for SCSS import');
        }
    }

    /**
     * Returns the Resource found at the given absolute path.
     */
    private function getResourceFromAbsolutePath(string $path): ?Resource
    {
        foreach ($this->sourceThemelets as $themelet) {
            if (!Path::isBasePath($themelet->getAbsolutePath(), $path)) {
                continue;
            }

            foreach ($themelet->getNamespaceAbsolutePaths() as $namespace => $namespacePaths) {
                foreach ($namespacePaths as $namespacePath) {
                    if (!Path::isBasePath($namespacePath, $path)) {
                        continue;
                    }

                    $locator = ThemeletLocator::fromNamespaceRelativeIdentifier(
                        $namespace,
                        Path::makeRelative($path, $namespacePath)
                    );

                    $resource = $themelet->getResource($locator);

                    if ($resource === null) {
                        continue 3; // continue search in other Themelets
                    } else {
                        return $resource;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Copies the given Resources to a single directory.
     *
     * @param Resource[] $resources
     *
     * @see self::$useResolvedDirectory
     */
    private function prepareImportableResourceFiles(array $resources): void
    {
        $this->publication->filesystem->deleteDirectory(
            $this->getImportableResourceDirectory(),
            preserve: true,
        );

        foreach ($resources as $resource) {
            $cachePath = $this->getImportableResourceAbsolutePath($resource);

            $this->importableResourceFiles[$cachePath] = $resource;

            if (
                !file_exists($cachePath) ||
                filemtime($resource->getAbsolutePath()) !== filemtime($cachePath)
            ) {
                $this->publication->filesystem->ensureDirectoryExists(
                    dirname($cachePath)
                );

                $this->publication->filesystem->copy($resource->getAbsolutePath(), $cachePath);
            }
        }
    }

    /**
     * Returns the file path to the given Resource in the resolved Resources directory.
     *
     * @see self::$useResolvedDirectory
     */
    private function getImportableResourceAbsolutePath(Resource $resource): string
    {
        return
            $this->getImportableResourceDirectory() .
            '/' .
            $resource->getIdentifierPath()
        ;
    }

    /**
     * Returns the path to the resolved Resources directory.
     *
     * @see self::$useResolvedDirectory
     */
    private function getImportableResourceDirectory(): string
    {
        return
            Themelet::CACHE_BASE_PATH .
            $this->asset->getThemelet()->getIdentifier() .
            '/resolvedResources'
        ;
    }
}
