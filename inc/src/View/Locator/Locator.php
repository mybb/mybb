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
     *   type?: ViewletLocator::COMPONENT_*,
     *   namespace?: ViewletLocator::COMPONENT_*,
     * } $directives
     * @param array{
     *   type?: ResourceType,
     *   namespace?: string,
     * } $context
     *
     * @throws Exception
     */
    public static function fromString(string $string, array $directives = [], array $context = []): static
    {
        $class = StaticLocator::isStaticLocator($string) ? StaticLocator::class : ViewletLocator::class;

        return $class::fromString($string, $directives, $context);
    }

    abstract public static function composeString(array $components): string;

    abstract public static function decomposeString(string $string): array;

    /**
     * @throws Exception
     */
    public static function fromNamespaceRelativeIdentifier(string $namespace, string $identifier): static
    {
        return static::fromString(
            $identifier,
            [
                'type' => ViewletLocator::COMPONENT_SET,
                'namespace' => ViewletLocator::COMPONENT_UNSET,
            ],
            [
                'namespace' => $namespace,
            ],
        );
    }

    /**
     * @throws Exception
     */
    public static function fromDependencyIdentifier(string $identifier, self $locator): static
    {
        if (StaticLocator::isStaticLocator($identifier)) {
            return StaticLocator::fromString($identifier);
        } else {
            return ViewletLocator::fromString(
                $identifier,
                [
                    'type' => ViewletLocator::COMPONENT_CONTEXT,
                    'namespace' => ViewletLocator::COMPONENT_CONTEXT,
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
