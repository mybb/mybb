<?php

declare(strict_types=1);

namespace MyBB\Extensions\Plugin;

use Illuminate\Filesystem\Filesystem;
use MyBB\Extensions\Contracts\ViewExtensionInterface;
use MyBB\Extensions\Exception as ExtensionException;
use MyBB\Extensions\Extension;
use MyBB\Extensions\Traits\ViewExtensionTrait;
use MyBB\View\Viewlet\NamespaceType;

/**
 * An Extension with custom executable code.
 */
class Plugin extends Extension implements ViewExtensionInterface
{
    use ViewExtensionTrait;

    public const EXTENSION_TYPE_ABSOLUTE_BASE_PATH = MYBB_ROOT . 'inc/plugins';

    public const REPOSITORY_CLASS = Repository::class;


    public const PACKAGE_RELATIVE_VIEWLET_PATH = '/view';

    public const NAMESPACE_TYPE_ACCESS = [
        NamespaceType::EXTENSION_OWN,
    ];

    public const VIEWLET_DIRECT_NAMESPACE = true;

    /**
     * @throws ExtensionException if the Extension package name is invalid.
     */
    public function __construct(string $packageName, Filesystem $filesystem)
    {
        if (!self::codenameValid($packageName)) {
            throw new ExtensionException('Invalid Extension package name `' . $packageName . '`');
        }

        parent::__construct($packageName, $filesystem);
    }

    protected static function getManifestFields(): array
    {
        return [
            ...parent::getManifestFields(),
            'type' => [
                'required' => false,
                'type' => 'string',
                'value' => 'mybb-plugin',
            ],
        ];
    }

    public function exists(): bool
    {
        return (
            parent::exists() ||
            $this->filesystem->isFile(
                $this->getLegacyManifestFilePath()
            )
        );
    }

    public function getManifest(): ?array
    {
        return parent::getManifest() ?? $this->getLegacyManifest();
    }


    public function getViewletDirectNamespace(): string
    {
        return NamespaceType::EXTENSION->getNamespaceFromIdentifier(
            $this->getPackageName(),
        );
    }

    private function getLegacyManifest(): ?array
    {
        $absolutePath = $this->getLegacyManifestFilePath();

        if ($this->filesystem->isFile($absolutePath)) {
            $this->filesystem->requireOnce($absolutePath);

            $infoFunctionName = $this->getPackageName() . '_info';

            if (function_exists($infoFunctionName)) {
                $info = $infoFunctionName();

                if (is_array($info)) {
                    $this->manifest = $info;

                    return $this->manifest;
                }
            }
        }

        return null;
    }

    private function getLegacyManifestFilePath(): string
    {
        return self::EXTENSION_TYPE_ABSOLUTE_BASE_PATH . '/' . $this->getPackageName() . '.php';
    }
}
