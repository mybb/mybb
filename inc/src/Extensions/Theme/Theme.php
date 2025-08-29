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
use MyBB\View\NamespaceType;

/**
 * An Extension customizing the GUI.
 */
class Theme extends Extension implements ViewExtensionInterface, HierarchicalExtensionInterface
{
    use ViewExtensionTrait;
    use HierarchicalExtensionTrait;

    public const EXTENSION_TYPE_ABSOLUTE_BASE_PATH = MYBB_ROOT . 'inc/themes/';

    public const REPOSITORY_CLASS = Repository::class;


    public const PACKAGE_RELATIVE_THEMELET_PATH = ''; // same directory

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
        parent::__construct($packageName, $filesystem);

        $this->type =
            ThemeType::tryFromPackageName($packageName)
            ?? throw new ExtensionException('Invalid Extension package name `' . $packageName . '`')
        ;

        $this->manifestFields['type'] = [
            'required' => false,
            'type' => 'string',
            'value' => 'mybb-theme',
        ];
        $this->manifestFields['extra.inherits'] = [
            'required' => false,
            'type' => 'array',
        ];
    }

    public function getType(): ThemeType
    {
        return $this->type;
    }

    public function getAbsolutePath(): string
    {
        return static::EXTENSION_TYPE_ABSOLUTE_BASE_PATH . $this->getPackageName();
    }

    private function canInheritFrom(self $extension): bool
    {
        $types = ThemeType::cases();

        $ownPriority = array_search($this->getType(), $types);
        $targetPriority = array_search($extension->getType(), $types);

        return $ownPriority <= $targetPriority;
    }
}
