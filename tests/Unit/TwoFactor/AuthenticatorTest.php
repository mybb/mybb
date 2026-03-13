<?php

namespace MyBB\Tests\Unit\TwoFactor;

use chillerlan\Authenticator\AuthenticatorOptions;
use chillerlan\Authenticator\Authenticators\TOTP;
use InvalidArgumentException;
use MyBB\Tests\Unit\TestCase;
use MyBB\TwoFactor\Authenticator;

class AuthenticatorTest extends TestCase
{
    private const TEST_SECRET = 'AAAAAAAAAAAAAAAAAAAAAAAAAA';

    public function testCreateSecretFormat()
    {
        $secret = (new Authenticator())->createSecret(16);

        $this->assertSame(26, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testVerifyCode()
    {
        $options = new AuthenticatorOptions([
            'adjacent' => 1,
        ]);

        $totp = new TOTP($options);
        $totp->setSecret(self::TEST_SECRET);
        $validCode = $totp->code();

        $authenticator = new Authenticator($options);

        $this->assertTrue($authenticator->verify(self::TEST_SECRET, $validCode));

        $invalidCode = $validCode;
        $invalidCode[0] = $validCode[0] === '0' ? '1' : '0';
        $this->assertFalse($authenticator->verify(self::TEST_SECRET, $invalidCode));
    }

    public function testGetUri()
    {
        $uri = (new Authenticator())->getUri(self::TEST_SECRET, 'admin@AdminCP', 'My Board');

        $this->assertSame('otpauth://totp/admin%40AdminCP?secret=' . self::TEST_SECRET . '&issuer=My%20Board', $uri);
    }

    public function testGetUriFallbackIssuer()
    {
        $uri = (new Authenticator())->getUri(self::TEST_SECRET, 'admin@AdminCP', '  ');

        $this->assertSame('otpauth://totp/admin%40AdminCP?secret=' . self::TEST_SECRET . '&issuer=MyBB', $uri);
    }

    public function testGetUriRejectsEmptyLabel()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$label argument cannot be empty');

        (new Authenticator())->getUri(self::TEST_SECRET, '   ', 'My Board');
    }
}
