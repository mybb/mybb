<?php

declare(strict_types=1);

namespace MyBB\View\Locator;

use MyBB\View\ResourceType;

/**
 * A reference to an Asset or Resource.
 */
abstract class Locator
{
    /**
     * @param array{
     *   type?: ThemeletLocator::COMPONENT_*,
     *   namespace?: ThemeletLocator::COMPONENT_*,
     * } $directives
     * @param array{
     *   type?: ResourceType,
     *   namespace?: string,
     * } $context
     */
    public static function fromString(string $string, array $directives = [], array $context = []): static
    {
        $class = StaticLocator::isStaticLocator($string) ? StaticLocator::class : ThemeletLocator::class;

        return $class::fromString($string, $directives, $context);
    }

    abstract public static function composeString(array $components): string;

    abstract public static function decomposeString(string $string): array;

    public static function fromNamespaceRelativeIdentifier(string $namespace, string $identifier): static
    {
        return self::fromString(
            $identifier,
            [
                'type' => ThemeletLocator::COMPONENT_SET,
                'namespace' => ThemeletLocator::COMPONENT_UNSET,
            ],
            [
                'namespace' => $namespace,
            ],
        );
    }

    public static function fromDependencyIdentifier(string $identifier, self $locator): static
    {
        if (StaticLocator::isStaticLocator($identifier)) {
            return StaticLocator::fromString($identifier);
        } else {
            return ThemeletLocator::fromString(
                $identifier,
                [
                    'type' => ThemeletLocator::COMPONENT_CONTEXT,
                    'namespace' => ThemeletLocator::COMPONENT_CONTEXT,
                ],
                [
                    'type' => ResourceType::tryFromFilename($identifier),
                    'namespace' => $locator->getNamespace(),
                ],
            );
        }
    }

    abstract public function getString(array $directives = [], array $context = []): string;

    abstract public function getNamespaceRelativeIdentifier(): string;
}
