<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\SpomkyLabs\Pki\CryptoTypes\Signature;

use OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Type\Primitive\BitString;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Asymmetric\Ed25519AlgorithmIdentifier;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Asymmetric\Ed448AlgorithmIdentifier;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Feature\AlgorithmIdentifierType;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Signature\ECSignatureAlgorithmIdentifier;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Signature\RSASignatureAlgorithmIdentifier;
/**
 * Base class for signature values.
 */
abstract class Signature
{
    /**
     * Get the signature as a BitString.
     */
    abstract public function bitString(): BitString;
    /**
     * Get signature object by signature data and used algorithm.
     *
     * @param string $data Signature value
     * @param AlgorithmIdentifierType $algo Algorithm identifier
     */
    public static function fromSignatureData(string $data, AlgorithmIdentifierType $algo): self
    {
        if ($algo instanceof RSASignatureAlgorithmIdentifier) {
            return RSASignature::fromSignatureString($data);
        }
        if ($algo instanceof ECSignatureAlgorithmIdentifier) {
            return ECSignature::fromDER($data);
        }
        if ($algo instanceof Ed25519AlgorithmIdentifier) {
            return Ed25519Signature::create($data);
        }
        if ($algo instanceof Ed448AlgorithmIdentifier) {
            return Ed448Signature::create($data);
        }
        return GenericSignature::create(BitString::create($data), $algo);
    }
}