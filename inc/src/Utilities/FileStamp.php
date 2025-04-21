<?php

declare(strict_types=1);

namespace MyBB\Utilities;

use InvalidArgumentException;
use LogicException;

readonly class FileStamp
{
    public const TYPE_CHECKSUM = 'checksum';
    public const TYPE_MODIFICATION_TIME = 'time';

    private const HASH_ALGORITHM = 'xxh128';

    /**
     * @param ?array<self::TYPE_*, int|string> $stamp
     */
    public function __construct(
        private ?array $stamp,
    ) {}

    public static function fromFile(?string $path = null, ?string $content = null): self
    {
        $values = [];

        foreach ([self::TYPE_CHECKSUM, self::TYPE_MODIFICATION_TIME] as $type) {
            $values[$type] = self::getValue($type, $path, $content);
        }

        return new self($values);
    }

    public static function fromNonexistentFile(): self
    {
        return new self(null);
    }

    /**
     * @param self::TYPE_* $type
     */
    private static function getValue(string $type, ?string $path = null, ?string $content = null): mixed
    {
        switch ($type) {
            case self::TYPE_CHECKSUM:
                if ($content !== null) {
                    $value = hash(self::HASH_ALGORITHM, $content);
                } elseif ($path !== null && is_readable($path)) {
                    $value = hash_file(self::HASH_ALGORITHM, $path);
                } else {
                    $value = null;
                }

                break;
            case self::TYPE_MODIFICATION_TIME:
                if (is_readable($path)) {
                    $value = filemtime($path);
                } else {
                    $value = null;
                }

                break;
            default:
                throw new InvalidArgumentException();
        }

        return $value;
    }

    /**
     * @return ?array<self::TYPE_*, int|string> $values
     */
    public function getStamp(): ?array
    {
        return $this->stamp;
    }

    /**
     * @param self::TYPE_* $type
     */
    public function isValid(string $path, string $type = self::TYPE_CHECKSUM): bool
    {
        if ($this->stamp === null) {
            return !file_exists($path);
        }

        if (!array_key_exists($type, $this->stamp)) {
            throw new LogicException('Cannot validate Stamp for `' . $path . '` - type `' . $type . '` is not set');
        }

        return $this->stamp[$type] === self::getValue($type, $path);
    }
}
