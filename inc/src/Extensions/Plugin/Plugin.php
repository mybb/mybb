<?php

declare(strict_types=1);

namespace MyBB\Extensions\Plugin;

use Exception;
use Illuminate\Filesystem\Filesystem;
use MyBB\Extensions\Contracts\ViewExtensionInterface;
use MyBB\Extensions\Extension;
use MyBB\Extensions\Traits\ViewExtensionTrait;
use MyBB\View\NamespaceType;

class Plugin extends Extension implements ViewExtensionInterface
{
    use ViewExtensionTrait;

    public const EXTENSION_TYPE_ABSOLUTE_BASE_PATH = MYBB_ROOT . 'inc/plugins/';

    public const REPOSITORY_CLASS = Repository::class;


    public const PACKAGE_RELATIVE_THEMELET_PATH = '/view';

    public const NAMESPACE_TYPE_ACCESS = [
        NamespaceType::EXTENSION_OWN,
    ];

    public const THEMELET_DIRECT_NAMESPACE = true;

    public function __construct(string $packageName, Filesystem $filesystem)
    {
        parent::__construct($packageName, $filesystem);

        if (!self::codenameValid($packageName)) {
            throw new Exception('Invalid Extension package name `' . $packageName . '`');
        }

        $this->manifestFields['type'] = [
            'required' => false,
            'type' => 'string',
            'value' => 'mybb-plugin',
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

    public function getThemeletDirectNamespace(): string
    {
        return NamespaceType::EXTENSION->getNamespaceFromIdentifier(
            $this->getPackageName(),
        );
    }

    public function getManifest(): ?array
    {
        return parent::getManifest() ?? $this->getLegacyManifest();
    }

    private function getLegacyManifest(): ?array
    {
        $absolutePath = self::EXTENSION_TYPE_ABSOLUTE_BASE_PATH . $this->getPackageName() . '.php';

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
}
