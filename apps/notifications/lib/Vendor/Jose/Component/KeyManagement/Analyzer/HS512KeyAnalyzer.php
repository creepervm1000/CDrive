<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\KeyManagement\Analyzer;

use Override;
final readonly class HS512KeyAnalyzer extends HSKeyAnalyzer
{
    #[Override]
    protected function getAlgorithmName(): string
    {
        return 'HS512';
    }
    #[Override]
    protected function getMinimumKeySize(): int
    {
        return 512;
    }
}