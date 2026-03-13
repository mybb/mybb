<?php

namespace MyBB\TwoFactor;

use chillerlan\Authenticator\AuthenticatorOptions;
use chillerlan\Authenticator\Authenticators\TOTP;
use chillerlan\Settings\SettingsContainerInterface;
use InvalidArgumentException;

class Authenticator
{
    protected TOTP $totp;

    public function __construct(?SettingsContainerInterface $options = null)
    {
        $this->totp = new TOTP($options ?? new AuthenticatorOptions());
    }

    /**
     * Create a new TOTP secret.
     */
    public function createSecret(int $length = 16): string
    {
        return $this->totp->createSecret($length);
    }

    /**
     * Verify a one-time password against a secret.
     */
    public function verify(string $secret, string $code): bool
    {
        $this->totp->setSecret($secret);

        return $this->totp->verify($code);
    }

    /**
     * Build an otpauth URI for a secret/account pair.
     */
    public function getUri(string $secret, string $label, string $issuer): string
    {
        $label = trim($label);
        $issuer = trim($issuer);

        if ($label === '') {
            throw new InvalidArgumentException('$label argument cannot be empty');
        }

        if ($issuer === '') {
            $issuer = 'MyBB';
        }

        $this->totp->setSecret($secret);

        return $this->totp->getUri($label, $issuer);
    }
}
