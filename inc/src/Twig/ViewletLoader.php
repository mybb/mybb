<?php

declare(strict_types=1);

namespace MyBB\Twig;

use MyBB\View\Locator\ViewletLocator;
use MyBB\View\ResourceType;
use MyBB\View\Viewlet\ViewletInterface;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;

/**
 * Loads templates from the provided Viewlet.
 */
class ViewletLoader extends FilesystemLoader
{
    public function __construct(
        private readonly ViewletInterface $viewlet,
        private readonly ?string $mainNamespace = null,
    ) {
        parent::__construct();
    }

    /**
     * @return string|null
     */
    protected function findTemplate(string $name, bool $throw = true)
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        if (isset($this->errorCache[$name])) {
            if (!$throw) {
                return null;
            }

            throw new LoaderError($this->errorCache[$name]);
        }

        $locator = ViewletLocator::fromResourceContextString(
            $name,
            ResourceType::TEMPLATE,
            $this->mainNamespace,
        );

        $path = $this->viewlet->getExistingResource($locator)?->getAbsolutePath();

        if ($path !== null) {
            $this->cache[$name] = $path;

            return $path;
        }

        $this->errorCache[$name] = sprintf('Unable to find template "%s"', $name);

        if (!$throw) {
            return null;
        }

        throw new LoaderError($this->errorCache[$name]);
    }
}
