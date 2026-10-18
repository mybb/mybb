<?php

declare(strict_types=1);

namespace MyBB\Captcha;

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\Exception\CaptchaException;
use Gregwar\Captcha\PhraseBuilder;
use InvalidArgumentException;

class Generator
{
    /**
     * Create a random CAPTCHA phrase.
     *
     * @param int $length Number of characters to generate, must be positive.
     * @throws InvalidArgumentException If the length is less than one.
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
     *
     * @param string $phrase Non-empty phrase to display in the image.
     * @return string Raw PNG bytes for a 200 by 60 pixel image.
     * @throws InvalidArgumentException If the phrase is empty.
     * @throws CaptchaException If image generation fails.
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
     *
     * @param string $phrase Expected phrase stored with the challenge.
     * @param string $input Submitted answer, without trimming or character substitutions.
     * @return bool Whether the answer matches a non-empty phrase.
     */
    public function verify(string $phrase, string $input): bool
    {
        return $phrase !== '' && hash_equals(strtolower($phrase), strtolower($input));
    }
}
