<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\KeyManagement\Analyzer;

use OCA\Notifications\Vendor\Jose\Component\Core\Util\Ecc\Curve;
use OCA\Notifications\Vendor\Jose\Component\Core\Util\Ecc\NistCurve;
use Override;
final readonly class ES512KeyAnalyzer extends ESKeyAnalyzer
{
    #[Override]
    protected function getAlgorithmName(): string
    {
        return 'ES512';
    }
    #[Override]
    protected function getCurveName(): string
    {
        return 'P-521';
    }
    #[Override]
    protected function getCurve(): Curve
    {
        return NistCurve::curve521();
    }
    #[Override]
    protected function getKeySize(): int
    {
        return 528;
    }
}