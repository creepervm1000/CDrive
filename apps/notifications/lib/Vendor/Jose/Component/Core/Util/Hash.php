<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Core\Util;

use InvalidArgumentException;
/**
 * @internal
 */
final readonly class Hash
{
    /**
     * @param positive-int $length
     */
    private function __construct(private string $hash, private int $length, private string $t)
    {
    }
    public static function get(string $function): self
    {
        return match ($function) {
            'sha1' => self::sha1(),
            'sha256' => self::sha256(),
            'sha384' => self::sha384(),
            'sha512' => self::sha512(),
            default => throw new InvalidArgumentException('Unsupported hash function'),
        };
    }
    public static function sha1(): self
    {
        return new self('sha1', 20, "0!0\t\x06\x05+\x0e\x03\x02\x1a\x05\x00\x04\x14");
    }
    public static function sha256(): self
    {
        return new self('sha256', 32, "010\r\x06\t`\x86H\x01e\x03\x04\x02\x01\x05\x00\x04 ");
    }
    public static function sha384(): self
    {
        return new self('sha384', 48, "0A0\r\x06\t`\x86H\x01e\x03\x04\x02\x02\x05\x00\x040");
    }
    public static function sha512(): self
    {
        return new self('sha512', 64, "0Q0\r\x06\t`\x86H\x01e\x03\x04\x02\x03\x05\x00\x04@");
    }
    /**
     * @return positive-int
     */
    public function getLength(): int
    {
        return $this->length;
    }
    /**
     * Compute the HMAC.
     */
    public function hash(string $text): string
    {
        return hash($this->hash, $text, true);
    }
    public function name(): string
    {
        return $this->hash;
    }
    public function t(): string
    {
        return $this->t;
    }
}