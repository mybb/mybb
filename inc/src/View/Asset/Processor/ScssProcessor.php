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

class ScssProcessor extends Processor
{
    /**
     * @var ThemeletInterface[]
     */
    protected array $sourceThemelets = [];

    /**
     * Use a temporary directory with resolved Resource files.
     *
     * As ScssPhp prioritizes relative paths over provided import paths/functions,
     * the files may be copied - according to inheritance - into a single location first.
     */
    private bool $useResolvedDirectory = false;

    /**
     * A mapping of absolute paths to importable Resources.
     *
     * @see $useResolvedDirectory
     *
     * @var array<string, Resource>
     */
    private array $importableResourceFiles = [];

    public function getOutputContent(): string
    {
        $importableResources = $this->getImportableResources();

        $originThemelets = $this->getResourceThemelets($importableResources);

        if (count($originThemelets) > 1) {
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

    private function getImportableResources(): array
    {
        return $this->asset->getThemelet()->getResources(
            [$this->asset->getResource()->getNamespace()],
            [$this->asset->getResource()->getType()],
        );
    }

    /**
     * @param Resource[] $resources
     */
    private function getResourceThemelets(array $resources): array
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
     * @see Compiler::resolveImportPath
     * @return string[]
     */
    private function getImportCandidatePaths(string $path): array
    {
        $candidatePaths = [];

        $pathsWithExtension = [];

        if (preg_match('/.s[ac]ss$/', $path)) {
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
     * @param string[] $paths
     */
    private function addSourcesFromAbsolutePaths(array $paths): void
    {
        foreach ($paths as $path) {
            $path = Path::normalize($path);

            if (array_key_exists($path, $this->importableResourceFiles)) {
                $resource = $this->importableResourceFiles[$path];

                $this->publication->addSource($resource);

                continue;
            }

            foreach ($this->sourceThemelets as $themelet) {
                if (!Path::isBasePath($themelet->getAbsolutePath(), $path)) {
                    continue;
                }

                foreach ($themelet->getNamespaceAbsolutePaths() as $namespace => $namespacePaths) {
                    foreach ($namespacePaths as $namespacePath) {
                        if (!Path::isBasePath($namespacePath, $path)) {
                            continue;
                        }

                        $this->publication->addSource(
                            $themelet->getExistingResource(
                                ThemeletLocator::fromNamespaceRelativeIdentifier(
                                    $namespace,
                                    Path::makeRelative($path, $namespacePath)
                                )
                            )
                        );

                        continue 4;
                    }
                }
            }

            throw new Exception('Unexpected Resource `' . $path . '` used for SCSS import');
        }
    }

    /**
     * @param Resource[] $resources
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
                $this->publication->filesystem->ensureDirectoryExists($cachePath);

                $this->publication->filesystem->copy($resource->getAbsolutePath(), $cachePath);
            }
        }
    }

    private function getImportableResourceAbsolutePath(Resource $resource): string
    {
        return
            $this->getImportableResourceDirectory() .
            '/' .
            $resource->getIdentifierPath()
        ;
    }

    private function getImportableResourceDirectory(): string
    {
        return
            Themelet::CACHE_BASE_PATH .
            $this->asset->getThemelet()->getIdentifier() .
            '/resolvedResources'
        ;
    }
}
