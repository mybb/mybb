<?php

declare(strict_types=1);

namespace MyBB\Utilities;

use Exception;

readonly class Encryptor
{
    /**
     * @throws Exception
     */
    public function __construct(private string $encryptionKey, private string $cipher = 'aes-256-cbc')
    {
        if (strlen($this->encryptionKey) !== 32) {
            throw new Exception('Encryption key must be 32 bytes (256 bits) long.');
        }
    }

    public function encrypt(string $data): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));

        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        return base64_encode($iv.$encrypted);
    }

    public function decrypt(string $data): string|false
    {
        $data = base64_decode($data);

        $ivLength = openssl_cipher_iv_length($this->cipher);

        return openssl_decrypt(
            substr($data, $ivLength),
            $this->cipher,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            substr($data, 0, $ivLength)
        );
    }
}
