<?php

declare(strict_types=1);

namespace MyBB\View\Locator;

use Exception;
use InvalidArgumentException;
use MyBB\View\ResourceType;

/**
 * A reference to a file in a Themelet structure.
 */
class ThemeletLocator extends Locator
{
    final public const COMPONENT_SET = 2;
    final public const COMPONENT_OPTIONAL = 4;
    final public const COMPONENT_CONTEXT = 8;
    final public const COMPONENT_UNSET = 16;

    private const NAMESPACE_PREFIX = '@';
    private const DIRECTORY_SEPARATOR = '/';

    public readonly ?ResourceType $type;
    public readonly ?string $namespace;
    public readonly ?string $group;
    public readonly ?string $filename;

    public function __construct(array $components = [])
    {
        foreach ($components as $component => $value) {
            $this->$component = $value;
        }
    }

    /**
     * @param array{
     *   type?: self::COMPONENT_*,
     *   namespace?: self::COMPONENT_*,
     * } $directives
     * @param array{
     *   type?: ?ResourceType,
     *   namespace?: ?string,
     * } $context
     */
    public static function fromString(string $string, array $directives = [], array $context = []): static
    {
        $components = static::decomposeString($string);

        static::validate($components, $context, $directives);

        $locator = new static();

        foreach ($components as $component => $value) {
            if ($value === null && isset($context[$component])) {
                $value = $context[$component];
            }

            $locator->$component = $value;
        }

        return $locator;
    }

    public static function fromResourceContextString(string $string, ResourceType $type, ?string $contextNamespace): static
    {
        return static::fromString(
            $string,
            [
                'type' => ThemeletLocator::COMPONENT_UNSET,
                'namespace' => $contextNamespace === null
                    ? ThemeletLocator::COMPONENT_SET
                    : ThemeletLocator::COMPONENT_CONTEXT
                ,
            ],
            [
                'type' => $type,
                'namespace' => $contextNamespace,
            ],
        );
    }

    /**
     * @param array{
     *   type?: ?ResourceType,
     *   namespace?: ?string,
     *   group?: ?string,
     *   filename?: ?string,
     * } $components
     */
    public static function composeString(array $components): string
    {
        $string = '';

        if (isset($components['namespace'])) {
            $string .= self::NAMESPACE_PREFIX . $components['namespace'] . self::DIRECTORY_SEPARATOR;
        }

        if (isset($components['type'])) {
            $string .= $components['type']->getPlural() . self::DIRECTORY_SEPARATOR;
        }

        if (isset($components['group'])) {
            $string .= $components['group'] . self::DIRECTORY_SEPARATOR;
        }

        if (isset($components['filename'])) {
            $string .= $components['filename'];
        }

        return $string;
    }

    /**
     * @return array{
     *   type: ?ResourceType,
     *   namespace: ?string,
     *   group: ?string,
     *   filename: ?string,
     * }
     */
    public static function decomposeString(string $string): array
    {
        $components = [
            'type' => null,
            'namespace' => null,
            'group' => null,
            'filename' => null,
        ];

        if (
            \DIRECTORY_SEPARATOR !== self::DIRECTORY_SEPARATOR &&
            str_contains($string, '\\')
        ) {
            throw new InvalidArgumentException('Cannot use non-normalized backslash `\` in Locator: `' . $string . '`');
        }

        $offset = 0;

        // namespace
        $firstNamespacePrefixPosition = strpos($string, self::NAMESPACE_PREFIX, $offset);
        $firstDirectorySeparatorPosition = strpos($string, self::DIRECTORY_SEPARATOR, $offset);

        if (
            $firstNamespacePrefixPosition !== false &&
            $firstDirectorySeparatorPosition !== false &&

            $firstNamespacePrefixPosition < $firstDirectorySeparatorPosition
        ) {
            $offset += strlen(self::NAMESPACE_PREFIX);

            $components['namespace'] = substr($string, $offset, $firstDirectorySeparatorPosition - $offset);

            $offset = $firstDirectorySeparatorPosition + 1;
        }

        // type
        $typeSeparatorPosition = strpos($string, self::DIRECTORY_SEPARATOR, $offset);

        if ($typeSeparatorPosition !== false) {
            $type = ResourceType::tryFromPlural(
                substr($string, $offset, $typeSeparatorPosition - $offset)
            );

            if ($type !== null) {
                $components['type'] = $type;

                $offset = $typeSeparatorPosition + 1;
            }
        }

        // subPath
        $subPath  = substr($string, $offset);

        $lastDirectorySeparatorPosition = strrpos($subPath, self::DIRECTORY_SEPARATOR);

        if ($lastDirectorySeparatorPosition !== false) {
            $components['group'] = substr($subPath, 0, $lastDirectorySeparatorPosition);
            $components['filename'] = substr($subPath, $lastDirectorySeparatorPosition + 1);
        } else {
            $components['group'] = null;
            $components['filename'] = $subPath;
        }

        return $components;
    }

    /**
     * @param array{
     *   type: ?ResourceType,
     *   namespace: ?string,
     *   group: ?string,
     *   filename: ?string,
     * } $components
     * @param array{
     *   namespace: ?string,
     *   type: ?ResourceType,
     * } $context
     * @param array{
     *   type?: self::COMPONENT_*,
     *   namespace?: self::COMPONENT_*,
     * } $directives
     */
    private static function validate(array $components, array $context, array $directives): void
    {
        $directives['type'] ??= self::COMPONENT_SET;
        $directives['namespace'] ??= self::COMPONENT_SET;

        foreach (['type', 'namespace'] as $component) {
            if ($components[$component] === null) {
                if (
                    $directives[$component] === self::COMPONENT_SET ||
                    ($directives[$component] === self::COMPONENT_CONTEXT && !isset($context[$component]))
                ) {
                    throw new Exception('Missing ' . $component . ' in Locator');
                }
            } elseif ($directives[$component] === self::COMPONENT_UNSET) {
                throw new Exception($component . ' not allowed in Locator');
            }
        }
    }

    /**
     * @param array{
     *   type?: self::COMPONENT_SET | self::COMPONENT_CONTEXT | self::COMPONENT_UNSET,
     *   namespace?: self::COMPONENT_SET | self::COMPONENT_CONTEXT | self::COMPONENT_UNSET,
     * } $directives
     * @param array{
     *   namespace: ?string,
     *   type: ?ResourceType,
     * } $context
     */
    public function getString(array $directives = [], array $context = []): string
    {
        $directives['type'] ??= self::COMPONENT_SET;
        $directives['namespace'] ??= self::COMPONENT_SET;

        $context['namespace'] ??= null;
        $context['type'] ??= null;

        $components = [];

        foreach (['type', 'namespace'] as $component) {
            if ($directives[$component] !== self::COMPONENT_UNSET) {
                if ($directives[$component] === self::COMPONENT_CONTEXT && $context[$component] !== null) {
                    if ($this->$component !== null && $this->$component !== $context[$component]) {
                        $components[$component] = $this->$component;
                    }
                } elseif ($this->$component !== null) {
                    $components[$component] = $this->$component;
                } elseif ($context[$component] !== null) {
                    $components[$component] = $context[$component];
                } else {
                    throw new Exception('Missing ' . $component . ' for Locator');
                }
            }
        }

        if (!empty($this->group)) {
            $components['group'] = $this->group;
        }

        if (!empty($this->filename)) {
            $components['filename'] = $this->filename;
        } else {
            throw new Exception('Missing filename for Locator');
        }

        return self::composeString($components);
    }

    public function getNamespaceRelativeIdentifier(): string
    {
        return $this->getString([
            'namespace' => self::COMPONENT_UNSET,
        ]);
    }

    public function getType(): ?ResourceType
    {
        return $this->type;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getSubPath(): string
    {
        $subPath = '';

        if ($this->group !== null) {
            $subPath .= $this->group . '/';
        }

        $subPath .= $this->filename;

        return $subPath;
    }

    /**
     * Returns a Locator used for Resources and Assets with shared context.
     */
    public function getSibling(string $subPath): static
    {
        return static::fromString(
            $subPath,
            [
                'type' => ThemeletLocator::COMPONENT_UNSET,
                'namespace' => ThemeletLocator::COMPONENT_UNSET,
            ],
            [
                'type' => $this->getType(),
                'namespace' => $this->getNamespace(),
            ],
        );
    }
}
