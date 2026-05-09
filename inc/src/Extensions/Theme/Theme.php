<?php

declare(strict_types=1);

namespace MyBB\Extensions\Theme;

use Illuminate\Filesystem\Filesystem;
use MyBB\Extensions\Contracts\HierarchicalExtensionInterface;
use MyBB\Extensions\Contracts\ViewExtensionInterface;
use MyBB\Extensions\Exception as ExtensionException;
use MyBB\Extensions\Extension;
use MyBB\Extensions\Traits\HierarchicalExtensionTrait;
use MyBB\Extensions\Traits\ViewExtensionTrait;
use MyBB\View\Viewlet\NamespaceType;

/**
 * An Extension customizing the GUI.
 */
class Theme extends Extension implements ViewExtensionInterface, HierarchicalExtensionInterface
{
    use ViewExtensionTrait;
    use HierarchicalExtensionTrait;

    public const EXTENSION_TYPE_BASE_PATH = 'inc/themes';

    public const EXTENSION_TYPE_ABSOLUTE_BASE_PATH = MYBB_ROOT . self::EXTENSION_TYPE_BASE_PATH;

    public const REPOSITORY_CLASS = Repository::class;


    public const PACKAGE_RELATIVE_VIEWLET_PATH = ''; // same directory

    public const NAMESPACE_TYPE_ACCESS = [
        NamespaceType::GENERIC,
        NamespaceType::EXTENSION,
    ];

    private readonly ThemeType $type;

    /**
     * @throws ExtensionException if the Extension package name is invalid.
     */
    public function __construct(string $packageName, Filesystem $filesystem)
    {
        $this->type =
            ThemeType::tryFromPackageName($packageName)
            ?? throw new ExtensionException('Invalid Extension package name `' . $packageName . '`');

        parent::__construct($packageName, $filesystem);
    }

    protected static function getManifestFields(): array
    {
        return [
            ...parent::getManifestFields(),
            'type' => [
                'required' => false,
                'type' => 'string',
                'value' => 'mybb-theme',
            ],
            'extra.inherits' => [
                'required' => false,
                'type' => 'array',
            ],
            'extra.properties' => [
                'required' => false,
                'type' => 'array',
            ],
        ];
    }

    public function getPropertyDefaults(): array
    {
        $manifest = $this->getManifest();

        return $manifest !== null && is_array($manifest['extra']['properties'] ?? null)
            ? $manifest['extra']['properties']
            : [];
    }

    public function getType(): ThemeType
    {
        return $this->type;
    }

    private function canInheritFrom(self $extension): bool
    {
        $types = ThemeType::cases();

        $ownPriority = array_search($this->getType(), $types);
        $targetPriority = array_search($extension->getType(), $types);

        return $targetPriority <= $ownPriority;
    }
}
