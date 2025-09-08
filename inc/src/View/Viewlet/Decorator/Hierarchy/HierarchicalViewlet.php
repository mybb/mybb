<?php

declare(strict_types=1);

namespace MyBB\View\Viewlet\Decorator\Hierarchy;

use MyBB\Extensions\Contracts\HierarchicalExtensionInterface;
use MyBB\Extensions\Repository;
use MyBB\Utilities\FileStamp;
use MyBB\Utilities\ManagedValue\ManagedValue;
use MyBB\View\Optimization;
use MyBB\View\Viewlet\Decorator\ViewletDecorator;
use MyBB\View\Viewlet\Viewlet;
use MyBB\View\Viewlet\ViewletInterface;

/**
 * Adds awareness of parent and base extensions to a Viewlet.
 */
class HierarchicalViewlet extends ViewletDecorator
{
    use HierarchicalAssetsTrait;
    use HierarchicalNamespacesTrait;
    use HierarchicalResourcesTrait;

    public string $inheritanceManagedValueValidationType = FileStamp::TYPE_MODIFICATION_TIME;

    private ManagedValue $ancestors;

    /**
     * @var ViewletInterface[]
     */
    private array $baseViewlets = [];

    /**
     * @var array<string, ViewletInterface>
     */
    private array $viewlets;

    /**
     * @var array<string, ViewletInterface[]>
     */
    private array $viewletsByNamespace;

    /**
     * @param Repository<HierarchicalExtensionInterface> $extensionRepository The Repository with ancestor Extensions.
     */
    public function __construct(
        Viewlet $viewlet,
        private readonly Repository $extensionRepository,
        Optimization $optimization,
    )
    {
        parent::__construct($viewlet);

        $managedValueRepository = $this->getManagedValueRepository();

        $storeMode = $optimization->getDirective('hierarchy.cache')
            ? ManagedValue::MODE_DEFERRED
            : ManagedValue::MODE_PASSIVE
        ;

        $this->ancestors = $managedValueRepository->create('hierarchy.ancestors')
            ->withDefault(
                /**
                 * @type array<string, ViewletInterface>
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
                    $this->getViewlet(...),
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
     * @param ViewletInterface[] $viewlets
     */
    public function setBaseViewlets(array $viewlets): void
    {
        $this->baseViewlets = $viewlets;
    }

    public function getOwnViewlet(): Viewlet
    {
        /** @var Viewlet */
        return $this->getDecorated();
    }

    /**
     * Returns Viewlets by target namespace in descending priority.
     *
     * @return array<string, ViewletInterface>
     */
    public function getViewletsByNamespace(?string $namespace = null): array
    {
        if (!isset($this->viewletsByNamespace)) {
            $this->viewletsByNamespace = [];

            foreach ($this->getViewlets() as $viewlet) {
                $names = $viewlet->getNamespaces();

                foreach ($names as $name) {
                    $this->viewletsByNamespace[$name][] = $viewlet;
                }
            }
        }

        if ($namespace === null) {
            return $this->viewletsByNamespace;
        } else {
            return $this->viewletsByNamespace[$namespace] ?? [];
        }
    }

    public function getViewlet(string $identifier): ?ViewletInterface
    {
        return $this->extensionRepository->get($identifier)->getViewlet();
    }

    /**
     * Returns source Viewlets in descending priority.
     *
     * @return array<string, ViewletInterface>
     */
    private function getViewlets(): array
    {
        if (!isset($this->viewlets)) {
            $viewlets = [
                // the Viewlet itself
                $this->getOwnViewlet(),

                // the Viewlet's ancestors
                ...$this->getAncestors(),

                // the common inheritance base
                ...$this->baseViewlets,
            ];

            $this->viewlets = [];

            foreach ($viewlets as $viewlet) {
                $this->viewlets[$viewlet->getIdentifier()] = $viewlet;
            }
        }

        return $this->viewlets;
    }

    /**
     * @return array<string, ViewletInterface>
     */
    private function getAncestors(): array
    {
        return $this->ancestors->get();
    }

    /**
     * @return array<string, ViewletInterface>
     */
    private function buildAncestors(&$stamp = []): array
    {
        $results = [];
        $stamp = [];

        $extensions = [
            $this->getExtension(),
            ...$this->getExtension()->getAncestors($this->extensionRepository),
        ];

        foreach ($extensions as $extension) {
            if ($extension !== $this->getExtension()) {
                $results[$extension->getPackageName()] = $extension->getViewlet();
            }

            $stamp[$extension->getPackageName()] = $extension->getManifestStamp();
        }

        return $results;
    }

    private function ancestorsStampValid(array $stamp): bool
    {
        foreach ($stamp as $packageName => $manifestStamp) {
            $extension = $this->extensionRepository->get($packageName);

            if (
                !$extension->manifestStampValid(
                    $manifestStamp,
                    $this->inheritanceManagedValueValidationType,
                )
            ) {
                return false;
            }
        }

        return true;
    }
}
