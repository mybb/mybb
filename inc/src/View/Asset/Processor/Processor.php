<?php

declare(strict_types=1);

namespace MyBB\View\Asset\Processor;

use MyBB\View\Asset\Publication;
use MyBB\View\Asset\ThemeletAsset;

/**
 * Transforms the given content of an Asset before publication.
 */
abstract class Processor
{
    protected readonly string $inputContent;

    public function __construct(
        protected Publication $publication,
        protected ThemeletAsset $asset,
    )
    {}

    /**
     * Accepts the unprocessed content.
     */
    final public function setInputContent(string $content): void
    {
        $this->inputContent = $content;
    }

    /**
     * Returns the processed content.
     */
    public function getOutputContent(): string
    {
        return $this->inputContent;
    }
}
