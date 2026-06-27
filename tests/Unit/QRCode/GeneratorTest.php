<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\QRCode;

use MyBB\QRCode\Generator;
use MyBB\Tests\Unit\TestCase;

class GeneratorTest extends TestCase
{
    private const TEST_SECRET = 'AAAAAAAAAAAAAAAAAAAAAAAAAA';

    public function testRenderReturnsSvgDataUri()
    {
        $payload = 'otpauth://totp/admin%40AdminCP?secret=' . self::TEST_SECRET . '&issuer=My%20Board';
        $output = (new Generator())->render($payload);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $output);

        $encodedSvg = substr($output, strlen('data:image/svg+xml;base64,'));
        $svg = base64_decode($encodedSvg, true);

        $this->assertNotFalse($svg);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($svg));
        $this->assertSame('svg', $dom->documentElement?->localName);
    }
}
