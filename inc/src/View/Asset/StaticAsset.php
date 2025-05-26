<?php

declare(strict_types=1);

namespace MyBB\View\Asset;

use LogicException;
use MyBB\View\Locator\StaticLocator;
use MyBB\View\ResourceType;
use MyBB\View\Themelet\ThemeletInterface;

/**
 * An Asset not published from Themelet sources.
 */
class StaticAsset extends Asset
{
    public function __construct(
        readonly protected StaticLocator $locator,
        readonly protected ?ThemeletInterface $themelet = null,
        readonly protected ?string $declarationNamespace = null,
        protected ?ResourceType $type = null,
    ) {}

    public function getLocator(): StaticLocator
    {
        return $this->locator;
    }

    public function getType(): ?ResourceType
    {
        return $this->type ??= ResourceType::tryFromFilename(
            $this->locator->getPath()
        );
    }

    public function getAbsolutePath(): string
    {
        if (!$this->isInApplicationDirectory()) {
            throw new LogicException('Cannot call `' . __METHOD__ . '` on Assets outside of the application directory');
        }

        return MYBB_ROOT . str_replace('./', '', $this->getPublicPath());
    }

    /**
     * Return an HTTP-accessible path to the file.
     */
    public function getPublicPath(): string
    {
        return $this->locator->getPath();
    }

    public function getUrl(bool $useCdn = true): string
    {
        $url = parent::getUrl();

        if (
            $this->isInApplicationDirectory() &&
            $time = filemtime($this->getAbsolutePath())
        ) {
            $url .= '?t=' . $time;
        }

        return $url;
    }

    public function isInApplicationDirectory(): bool
    {
        return $this->locator->isCurrentDirectoryRelative();
    }

    protected function getEntityNamespace(): string
    {
        return $this->declarationNamespace;
    }
}
