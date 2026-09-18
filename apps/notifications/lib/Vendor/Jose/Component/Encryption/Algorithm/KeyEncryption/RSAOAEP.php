<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Encryption\Algorithm\KeyEncryption;

use OCA\Notifications\Vendor\Jose\Component\Encryption\Algorithm\KeyEncryption\Util\RSACrypt;
use Override;
final readonly class RSAOAEP extends RSA
{
    #[Override]
    public function name(): string
    {
        return 'RSA-OAEP';
    }
    #[Override]
    protected function getEncryptionMode(): int
    {
        return RSACrypt::ENCRYPTION_OAEP;
    }
    #[Override]
    protected function getHashAlgorithm(): string
    {
        return 'sha1';
    }
}