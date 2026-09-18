<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Encryption\Algorithm\ContentEncryption;

use Override;
final readonly class A192GCM extends AESGCM
{
    #[Override]
    public function getCEKSize(): int
    {
        return 192;
    }
    #[Override]
    public function name(): string
    {
        return 'A192GCM';
    }
    #[Override]
    protected function getMode(): string
    {
        return 'aes-192-gcm';
    }
}