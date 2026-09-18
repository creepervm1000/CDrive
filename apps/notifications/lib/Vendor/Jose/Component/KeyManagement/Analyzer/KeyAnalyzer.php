<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\KeyManagement\Analyzer;

use OCA\Notifications\Vendor\Jose\Component\Core\JWK;
interface KeyAnalyzer
{
    /**
     * This method will analyse the key and add messages to the message bag if needed.
     */
    public function analyze(JWK $jwk, MessageBag $bag): void;
}