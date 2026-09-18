<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\KeyManagement\Analyzer;

use OCA\Notifications\Vendor\Jose\Component\Core\JWK;
use Override;
final readonly class AlgorithmAnalyzer implements KeyAnalyzer
{
    #[Override]
    public function analyze(JWK $jwk, MessageBag $bag): void
    {
        if (!$jwk->has('alg')) {
            $bag->add(Message::medium('The parameter "alg" should be added.'));
        }
    }
}