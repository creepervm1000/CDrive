<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Encryption\Algorithm\KeyEncryption;

use Override;
final readonly class A128GCMKW extends AESGCMKW
{
    #[Override]
    public function name(): string
    {
        return 'A128GCMKW';
    }
    #[Override]
    protected function getKeySize(): int
    {
        return 128;
    }
}