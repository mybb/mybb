<?php

declare(strict_types=1);

namespace MyBB\View;

use MyBB\View\Runtime\Runtime;

use function MyBB\app;

const DEFAULT_THEME_PACKAGE = 'core.default';

/**
 * Passes data to Resources.
 *
 * @param array<string, scalar> $data
 */
function set(array $data): void
{
    app(Runtime::class)->setSharedData($data);
}

function directive(string $name): mixed
{
    return app(Optimization::class)->getDirective($name);
}
