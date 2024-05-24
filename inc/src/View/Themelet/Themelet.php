<?php

declare(strict_types=1);

namespace MyBB\View\Themelet;

use MyBB\Extensions\ViewExtensionInterface;
use MyBB\Utilities\Hydrable\FilesystemStore;
use MyBB\Utilities\Hydrable\Repository;

/**
 * A UI package containing Resources and metadata.
 */
class Themelet implements ThemeletInterface
{
    use AssetsTrait;
    use NamespacesTrait;
    use ResourcesTrait;

    public const CACHE_BASE_PATH = MYBB_ROOT . 'cache/themelets/';

    private ?ViewExtensionInterface $extension = null;

    private string $absolutePath;
    private readonly Repository $hydrableRepository;

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

    public function getHydrableRepository(): Repository
    {
        if (!isset($this->hydrableRepository)) {
            $store = new FilesystemStore(self::CACHE_BASE_PATH . $this->getIdentifier());

            $this->hydrableRepository = new Repository($store);
        }

        return $this->hydrableRepository;
    }

    private function __construct(?ViewExtensionInterface $extension = null)
    {
        if ($extension !== null) {
            $this->extension = $extension;

            $this->absolutePath = $extension->getThemeletAbsolutePath();

            $this->namespaceTypeAccess = $extension::NAMESPACE_TYPE_ACCESS;

            if ($extension::THEMELET_DIRECT_NAMESPACE) {
                $this->directNamespace = $extension->getThemeletDirectNamespace();
            }
        }
    }
}
