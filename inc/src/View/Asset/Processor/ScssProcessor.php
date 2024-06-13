<?php

declare(strict_types=1);

namespace MyBB\View\Asset\Processor;

use Exception;
use Illuminate\Filesystem\Filesystem;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Resource;
use MyBB\View\Themelet\Themelet;
use MyBB\View\Themelet\ThemeletInterface;
use ScssPhp\ScssPhp\Compiler;

class ScssProcessor extends Processor
{
    /**
     * @var ThemeletInterface[]
     */
    protected array $sourceThemelets = [];

    /**
     * A mapping of absolute paths to importable Resources.
     *
     * As ScssPhp prioritizes relative paths over provided import paths/functions,
     * the files may be copied - according to inheritance - into a single location first.
     *
     * @var array<string, Resource>
     */
    private array $importableResourceFiles = [];

    public function getOutputContent(): string
    {
        $importableResources = $this->getImportableResources();

        $originThemelets = $this->getResourceThemelets($importableResources);

        if (count($originThemelets) > 1) {
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
        static $resources;

        return $resources ??= $this->asset->getThemelet()->getResources(
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
                    fn (Resource $resource) => $resource->getThemelet()->getIdentifier(),
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

                    return $resource->getAbsolutePath();
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
            if (array_key_exists($path, $this->importableResourceFiles)) {
                $resource = $this->importableResourceFiles[$path];

                $this->publication->addSource($resource);

                continue;
            }

            foreach ($this->sourceThemelets as $themelet) {
                if (!str_starts_with($path, $themelet->getAbsolutePath() . '/')) {
                    continue;
                }

                foreach ($themelet->getNamespaceAbsolutePaths() as $namespace => $namespacePaths) {
                    foreach ($namespacePaths as $namespacePath) {
                        if (!str_starts_with($path, $namespacePath . '/')) {
                            continue;
                        }

                        $this->publication->addSource(
                            $themelet->getExistingResource(
                                ThemeletLocator::fromNamespaceRelativeIdentifier(
                                    $namespace,
                                    substr($path, strlen($namespacePath . '/'))
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
        $filesystem = new Filesystem();

        $filesystem->deleteDirectory(
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
                mkdir(
                    dirname($cachePath),
                    recursive: true,
                );

                copy(
                    $resource->getAbsolutePath(),
                    $cachePath,
                );
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
        static $path;

        return $path ??=
            Themelet::CACHE_BASE_PATH .
            $this->asset->getThemelet()->getIdentifier() .
            '/resolvedResources'
        ;
    }
}
