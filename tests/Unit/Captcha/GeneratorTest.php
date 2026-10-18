<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Captcha;

use InvalidArgumentException;
use MyBB\Captcha\Generator;
use MyBB\Tests\Unit\TestCase;

class GeneratorTest extends TestCase
{
    public function testGeneratePhraseFormat()
    {
        $phrase = (new Generator())->generatePhrase();

        $this->assertSame(5, strlen($phrase));
        $this->assertMatchesRegularExpression('/^[a-zA-Z1-9]+$/', $phrase);
    }

    public function testGeneratePhraseLength()
    {
        $this->assertSame(8, strlen((new Generator())->generatePhrase(8)));
    }

    public function testInvalidPhraseLength()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Generator())->generatePhrase(0);
    }

    public function testVerifyAnswer()
    {
        $generator = new Generator();

        $this->assertTrue($generator->verify('AbC12', 'aBc12'));
        $this->assertFalse($generator->verify('AbC12', 'AbC13'));
        $this->assertFalse($generator->verify('AbC12', ' AbC12 '));
        $this->assertFalse($generator->verify('AbC12', ''));
        $this->assertFalse($generator->verify('', ''));
    }

    public function testRenderPng()
    {
        $output = (new Generator())->render('AbC12');
        $image = imagecreatefromstring($output);

        $this->assertNotFalse($image);
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring($output)[2]);
        $this->assertSame(200, imagesx($image));
        $this->assertSame(60, imagesy($image));
    }

    public function testRejectsEmptyPhrase()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Generator())->render('');
    }
}
