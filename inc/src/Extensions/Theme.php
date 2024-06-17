<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use Exception;
use FilesystemIterator;
use MyBB\View\NamespaceType;
use SplFileInfo;

class Theme extends Extension implements ViewExtensionInterface, HierarchicalExtensionInterface
{
    use ViewExtensionTrait;
    use HierarchicalExtensionTrait;

    public const EXTENSION_TYPE_ABSOLUTE_BASE_PATH = MYBB_ROOT . 'inc/themes/';

    public const PACKAGE_RELATIVE_THEMELET_PATH = ''; // same directory

    public const NAMESPACE_TYPE_ACCESS = [
        NamespaceType::GENERIC,
        NamespaceType::EXTENSION,
    ];

    private readonly ThemeType $type;

    /**
     * @return array<string, self>
     */
    public static function getAll(): array
    {
        static $extensions;

        if (!isset($extensions)) {
            $directoryNames = array_keys(
                array_filter(
                    iterator_to_array(
                        new FilesystemIterator(
                            self::EXTENSION_TYPE_ABSOLUTE_BASE_PATH,
                            FilesystemIterator::KEY_AS_FILENAME
                            | FilesystemIterator::CURRENT_AS_FILEINFO
                            | FilesystemIterator::SKIP_DOTS
                        )
                    ),
                    fn (SplFileInfo $item) => $item->isDir(),
                )
            );

            foreach ($directoryNames as $packageName) {
                if (!ThemeType::tryFromPackageName($packageName)) {
                    throw new Exception('Invalid Extension package name `' . $packageName . '`');
                }

                $extensions[$packageName] = self::get($packageName);
            }
        }

        return $extensions;
    }

    public function __construct(string $packageName, ?string $version = null)
    {
        parent::__construct($packageName, $version);

        $this->type =
            ThemeType::tryFromPackageName($packageName)
            ?? throw new Exception('Invalid Extension package name `' . $packageName . '`')
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
