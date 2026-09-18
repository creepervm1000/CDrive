<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Encryption\Algorithm\KeyEncryption;

use AESKW\A256KW as Wrapper;
use AESKW\Wrapper as WrapperInterface;
use Override;
final readonly class A256KW extends AESKW
{
    #[Override]
    public function name(): string
    {
        return 'A256KW';
    }
    #[Override]
    protected function getWrapper(): WrapperInterface
    {
        return new Wrapper();
    }
}