<?php

declare(strict_types=1);

namespace MyBB\View;

use InvalidArgumentException;
use MyBB\Extensions\Extension;
use MyBB\Extensions\ViewExtensionInterface;

enum NamespaceType
{
    case GENERIC;
    case EXTENSION;
    case EXTENSION_OWN;

    /**
     * @return null | ($contextExtension is null ? (self::GENERIC | self::EXTENSION) : self)
     */
    public static function tryFromNamespace(string $namespace, ?ViewExtensionInterface $contextExtension = null): ?self
    {
        // try self::EXTENSION_OWN before self::EXTENSION
        foreach ([self::GENERIC, self::EXTENSION_OWN, self::EXTENSION] as $type) {
            if ($type->namespaceValid($namespace, $contextExtension)) {
                return $type;
            }
        }

        return null;
    }

    public function namespaceValid(string $namespace, ?ViewExtensionInterface $contextExtension = null): bool
    {
        return (
            str_starts_with($namespace, $this->getPrefix()) &&
            $this->namespaceIdentifierValid(
                $this->getNamespaceIdentifier($namespace),
                $contextExtension,
            )
        );
    }

    public function getNamespaceIdentifier(string $namespace): string
    {
        $prefix = $this->getPrefix();

        if (!str_starts_with($namespace, $prefix)) {
            throw new InvalidArgumentException();
        }

        return substr($namespace, strlen($prefix));
    }

    public function getPrefix(): string
    {
        return match ($this) {
            self::GENERIC => '',
            self::EXTENSION, self::EXTENSION_OWN => 'ext.',
        };
    }

    public function getNamespaceFromIdentifier(string $identifier): string
    {
        return $this->getPrefix() . $identifier;
    }

    private function namespaceIdentifierValid(string $identifier, ?ViewExtensionInterface $contextExtension): bool
    {
        return match ($this) {
            self::GENERIC =>
                ctype_alpha(str_replace('_', '', $identifier)),
            self::EXTENSION =>
                Extension::codenameValid($identifier),
            self::EXTENSION_OWN =>
                Extension::codenameValid($identifier) &&
                (
                    $contextExtension === null ||
                    $contextExtension->getPackageName() === $identifier
                )
            ,
        };
    }
}
