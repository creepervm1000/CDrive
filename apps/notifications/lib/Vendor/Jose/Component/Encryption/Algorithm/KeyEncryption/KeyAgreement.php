<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Encryption\Algorithm\KeyEncryption;

use OCA\Notifications\Vendor\Jose\Component\Core\JWK;
use OCA\Notifications\Vendor\Jose\Component\Encryption\Algorithm\KeyEncryptionAlgorithm;
interface KeyAgreement extends KeyEncryptionAlgorithm
{
    /**
     * Computes the agreement key.
     *
     * @param array<string, mixed> $completeHeader
     * @param array<string, mixed> $additionalHeaderValues
     */
    public function getAgreementKey(int $encryptionKeyLength, string $algorithm, JWK $recipientKey, ?JWK $senderKey, array $completeHeader = [], array &$additionalHeaderValues = []): string;
}