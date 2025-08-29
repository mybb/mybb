<?php

declare(strict_types=1);

namespace MyBB\Extensions\Theme;

/**
 * @extends \MyBB\Extensions\Repository<Theme>
 */
class Repository extends \MyBB\Extensions\Repository
{
    public const ENTITY_CLASS = Theme::class;
}
