<?php

declare(strict_types=1);

namespace MyBB\Cargo;

interface EntityInterface
{
    public function getProperties();
    public function setProperties(array $properties);
    public function declaredInherited(): bool;
    public function getRepository(): RepositoryInterface;
    public function getRepositoryEntityKey(): string;
}
