<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Encryption\Algorithm\KeyEncryption;

use Override;
final readonly class A256GCMKW extends AESGCMKW
{
    #[Override]
    public function name(): string
    {
        return 'A256GCMKW';
    }
    #[Override]
    protected function getKeySize(): int
    {
        return 256;
    }
}