<?php

declare(strict_types=1);

namespace MyBB\View\Viewlet;

use MyBB\Extensions\Contracts\ViewExtensionInterface;
use MyBB\Utilities\ManagedValue\Repository as ManagedValueRepository;

use function MyBB\app;

/**
 * A UI package containing Resources and metadata.
 */
class Viewlet implements ViewletInterface
{
    use AssetsTrait;
    use NamespacesTrait;
    use ResourcesTrait;

    public const CACHE_BASE_PATH = MYBB_ROOT . 'cache/viewlets/';

    private ?ViewExtensionInterface $extension = null;

    private string $absolutePath;
    private readonly ManagedValueRepository $managedValueRepository;

    public static function fromExtension(?ViewExtensionInterface $extension = null): self
    {
        return new self($extension);
    }

    public function getExtension(): ?ViewExtensionInterface
    {
        return $this->extension;
    }

    public function getIdentifier(): string
    {
        return $this->extension->getPackageName();
    }

    public function getAbsolutePath(): string
    {
        return $this->absolutePath;
    }

    public function getManagedValueRepository(): ManagedValueRepository
    {
        if (!isset($this->managedValueRepository)) {
            $this->managedValueRepository = app(ManagedValueRepository::class, [
                'path' => ['viewlets', $this->getIdentifier()],
            ]);
        }

        return $this->managedValueRepository;
    }

    private function __construct(?ViewExtensionInterface $extension = null)
    {
        if ($extension !== null) {
            $this->extension = $extension;

            $this->absolutePath = $extension->getViewletAbsolutePath();

            $this->namespaceTypeAccess = $extension::NAMESPACE_TYPE_ACCESS;

            if ($extension::VIEWLET_DIRECT_NAMESPACE) {
                $this->directNamespace = $extension->getViewletDirectNamespace();
            }
        }
    }
}
