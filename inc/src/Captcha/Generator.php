<?php

declare(strict_types=1);

namespace MyBB\Captcha;

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use InvalidArgumentException;

class Generator
{
    /**
     * Create a random CAPTCHA phrase.
     */
    public function generatePhrase(int $length = 5): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('$length argument must be positive');
        }

        return (new PhraseBuilder($length))->build();
    }

    /**
     * Render a CAPTCHA phrase as PNG image data.
     */
    public function render(string $phrase): string
    {
        if ($phrase === '') {
            throw new InvalidArgumentException('$phrase argument cannot be empty');
        }

        $builder = new CaptchaBuilder($phrase);
        $builder->setImageType('png');
        $builder->build(200, 60);

        return $builder->get();
    }

    /**
     * Verify an answer against a phrase, ignoring letter case.
     */
    public function verify(string $phrase, string $input): bool
    {
        return $phrase !== '' && hash_equals(strtolower($phrase), strtolower($input));
    }
}
