<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\Decorator\Hierarchy;

use MyBB\Utilities\FileStamp;
use MyBB\Utilities\ManagedValue\ManagedValue;
use MyBB\View\Optimization;
use MyBB\View\Themelet\Decorator\ThemeletDecorator;
use MyBB\View\Themelet\Themelet;
use MyBB\View\Themelet\ThemeletInterface;

/**
 * Adds awareness of parent and base extensions to a Themelet.
 */
class HierarchicalThemelet extends ThemeletDecorator
{
    use HierarchicalAssetsTrait;
    use HierarchicalNamespacesTrait;
    use HierarchicalResourcesTrait;

    public string $inheritanceManagedValueValidationType = FileStamp::TYPE_MODIFICATION_TIME;

    private ManagedValue $ancestors;

    /**
     * @var ThemeletInterface[]
     */
    private array $baseThemelets = [];

    /**
     * @var array<string, ThemeletInterface>
     */
    private array $themelets;

    /**
     * @var array<string, ThemeletInterface[]>
     */
    private array $themeletsByNamespace;

    public function __construct(
        Optimization $optimization,
    )
    {
        $managedValueRepository = $this->getManagedValueRepository();

        $storeMode = $optimization->getDirective('hierarchy.cache')
            ? ManagedValue::MODE_DEFERRED
            : ManagedValue::MODE_PASSIVE
        ;

        $this->ancestors = $managedValueRepository->create('hierarchy.ancestors')
            ->withDefault(
                /**
                 * @type array<string, ThemeletInterface>
                 */
                [],
            )
            ->withBuild($this->buildAncestors(...))
            ->withSave(
                array_keys(...),
                $storeMode,
            )
            ->withLoad(
                fn (array $value) => array_map(
                    $this->getThemelet(...),
                    $value,
                ),
                $storeMode,
            )
            ->withStampValidation(
                $this->ancestorsStampValid(...),
                $optimization->getDirective('hierarchy.cacheValidation')
                    ? ManagedValue::MODE_IMMEDIATE
                    : ManagedValue::MODE_PASSIVE,
            );
    }

    /**
     * @param ThemeletInterface[] $themelets
     */
    public function setBaseThemelets(array $themelets): void
    {
        $this->baseThemelets = $themelets;
    }

    public function getOwnThemelet(): Themelet
    {
        /** @var Themelet */
        return $this->getDecorated();
    }

    /**
     * Returns Themelets by target namespace in descending priority.
     *
     * @return array<string, ThemeletInterface>
     */
    public function getThemeletsByNamespace(?string $namespace = null): array
    {
        if (!isset($this->themeletsByNamespace)) {
            $this->themeletsByNamespace = [];

            foreach ($this->getThemelets() as $themelet) {
                $names = $themelet->getNamespaces();

                foreach ($names as $name) {
                    $this->themeletsByNamespace[$name][] = $themelet;
                }
            }
        }

        if ($namespace === null) {
            return $this->themeletsByNamespace;
        } else {
            return $this->themeletsByNamespace[$namespace] ?? [];
        }
    }

    public function getThemelet(string $identifier): ?ThemeletInterface
    {
        return $this->getExtension()::get($identifier)->getThemelet();
    }

    /**
     * Returns source Themelets in descending priority.
     *
     * @return array<string, ThemeletInterface>
     */
    private function getThemelets(): array
    {
        if (!isset($this->themelets)) {
            $themelets = [
                // the Themelet itself
                $this->getOwnThemelet(),

                // the Themelet's ancestors
                ...$this->getAncestors(),

                // the common inheritance base
                ...$this->baseThemelets,
            ];

            $this->themelets = [];

            foreach ($themelets as $themelet) {
                $this->themelets[$themelet->getIdentifier()] = $themelet;
            }
        }

        return $this->themelets;
    }

    /**
     * @return array<string, ThemeletInterface>
     */
    private function getAncestors(): array
    {
        return $this->ancestors->get();
    }

    /**
     * @return array<string, ThemeletInterface>
     */
    private function buildAncestors(&$stamp = []): array
    {
        $results = [];
        $stamp = [];

        $extensions = [
            $this->getExtension(),
            ...$this->getExtension()->getAncestors(),
        ];

        foreach ($extensions as $extension) {
            if ($extension !== $this->getExtension()) {
                $results[$extension->getPackageName()] = $extension->getThemelet();
            }

            $stamp[$extension->getPackageName()] = $extension->getManifestStamp();
        }

        return $results;
    }

    private function ancestorsStampValid(array $stamp): bool
    {
        $extensions = $this->getExtension()->getInheritanceChain();

        if (count($stamp) !== count($extensions)) {
            return false;
        }

        if (array_keys($extensions) !== array_keys($stamp)) {
            return false;
        }

        foreach ($extensions as $packageName => $extension) {
            if (
                !$extension->manifestStampValid(
                    $stamp[$packageName],
                    $this->inheritanceManagedValueValidationType,
                )
            ) {
                return false;
            }
        }

        return true;
    }
}
