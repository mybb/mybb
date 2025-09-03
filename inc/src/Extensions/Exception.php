<?php

declare(strict_types=1);

namespace MyBB\Extensions;

/**
 * Thrown when an Extension problem has occurred.
 */
class Exception extends \Exception
{
    public function __construct(
        $message,
        public readonly ?Extension $extension = null,
        ?\Exception $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
