<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Signature\Algorithm;

use Override;
final readonly class PS512 extends RSAPSS
{
    #[Override]
    public function name(): string
    {
        return 'PS512';
    }
    #[Override]
    protected function getAlgorithm(): string
    {
        return 'sha512';
    }
}