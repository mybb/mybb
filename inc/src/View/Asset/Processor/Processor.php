<?php

declare(strict_types=1);

namespace MyBB\View\Asset\Processor;

use MyBB\View\Asset\Publication;
use MyBB\View\Asset\ThemeletAsset;

abstract class Processor
{
    protected readonly string $inputContent;

    public function __construct(
        protected Publication $publication,
        protected ThemeletAsset $asset,
    )
    {}

    final public function setInputContent(string $content): void
    {
        $this->inputContent = $content;
    }

    public function getOutputContent(): string
    {
        return $this->inputContent;
    }
}
