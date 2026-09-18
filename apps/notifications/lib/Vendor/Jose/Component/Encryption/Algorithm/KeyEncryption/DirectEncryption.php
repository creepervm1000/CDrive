<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Encryption\Algorithm\KeyEncryption;

use OCA\Notifications\Vendor\Jose\Component\Core\JWK;
use OCA\Notifications\Vendor\Jose\Component\Encryption\Algorithm\KeyEncryptionAlgorithm;
interface DirectEncryption extends KeyEncryptionAlgorithm
{
    /**
     * Returns the CEK.
     *
     * @param JWK $key The key used to get the CEK
     */
    public function getCEK(JWK $key): string;
}