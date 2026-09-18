<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Core\Util;

use InvalidArgumentException;
use OCA\Notifications\Vendor\Jose\Component\Core\JWK;
use RuntimeException;
use function extension_loaded;
use function is_array;
use function is_string;
use function sprintf;
use const OPENSSL_KEYTYPE_EC;
use const STR_PAD_LEFT;
/**
 * @internal
 */
final readonly class ECKey
{
    public static function convertToPEM(JWK $jwk): string
    {
        if ($jwk->has('d')) {
            return self::convertPrivateKeyToPEM($jwk);
        }
        return self::convertPublicKeyToPEM($jwk);
    }
    public static function convertPublicKeyToPEM(JWK $jwk): string
    {
        $der = match ($jwk->get('crv')) {
            'P-256' => self::p256PublicKey(),
            'secp256k1' => self::p256KPublicKey(),
            'P-384' => self::p384PublicKey(),
            'P-521' => self::p521PublicKey(),
            default => throw new InvalidArgumentException('Unsupported curve.'),
        };
        $der .= self::getKey($jwk);
        $pem = '-----BEGIN PUBLIC KEY-----' . "\n";
        $pem .= chunk_split(base64_encode($der), 64, "\n");
        return $pem . ('-----END PUBLIC KEY-----' . "\n");
    }
    public static function convertPrivateKeyToPEM(JWK $jwk): string
    {
        $der = match ($jwk->get('crv')) {
            'P-256' => self::p256PrivateKey($jwk),
            'secp256k1' => self::p256KPrivateKey($jwk),
            'P-384' => self::p384PrivateKey($jwk),
            'P-521' => self::p521PrivateKey($jwk),
            default => throw new InvalidArgumentException('Unsupported curve.'),
        };
        $der .= self::getKey($jwk);
        $pem = '-----BEGIN EC PRIVATE KEY-----' . "\n";
        $pem .= chunk_split(base64_encode($der), 64, "\n");
        return $pem . ('-----END EC PRIVATE KEY-----' . "\n");
    }
    /**
     * Creates a EC key with the given curve and additional values.
     *
     * @param string $curve The curve
     * @param array $values values to configure the key
     */
    public static function createECKey(string $curve, array $values = []): JWK
    {
        $jwk = self::createECKeyUsingOpenSSL($curve);
        $values = array_merge($values, $jwk);
        return new JWK($values);
    }
    private static function getNistCurveSize(string $curve): int
    {
        return match ($curve) {
            'P-256', 'secp256k1' => 256,
            'P-384' => 384,
            'P-521' => 521,
            default => throw new InvalidArgumentException(sprintf('The curve "%s" is not supported.', $curve)),
        };
    }
    private static function createECKeyUsingOpenSSL(string $curve): array
    {
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('Please install the OpenSSL extension');
        }
        $key = openssl_pkey_new(['curve_name' => self::getOpensslCurveName($curve), 'private_key_type' => OPENSSL_KEYTYPE_EC, 'private_key_bits' => 2048]);
        if ($key === false) {
            throw new RuntimeException('Unable to create the key');
        }
        $result = openssl_pkey_export($key, $out);
        if ($result === false) {
            throw new RuntimeException('Unable to create the key');
        }
        $res = openssl_pkey_get_private($out);
        if ($res === false) {
            throw new RuntimeException('Unable to create the key');
        }
        $details = openssl_pkey_get_details($res);
        if ($details === false) {
            throw new InvalidArgumentException('Unable to get the key details');
        }
        $nistCurveSize = self::getNistCurveSize($curve);
        return ['kty' => 'EC', 'crv' => $curve, 'd' => Base64UrlSafe::encodeUnpadded(str_pad((string) $details['ec']['d'], (int) ceil($nistCurveSize / 8), "\x00", STR_PAD_LEFT)), 'x' => Base64UrlSafe::encodeUnpadded(str_pad((string) $details['ec']['x'], (int) ceil($nistCurveSize / 8), "\x00", STR_PAD_LEFT)), 'y' => Base64UrlSafe::encodeUnpadded(str_pad((string) $details['ec']['y'], (int) ceil($nistCurveSize / 8), "\x00", STR_PAD_LEFT))];
    }
    private static function getOpensslCurveName(string $curve): string
    {
        return match ($curve) {
            'P-256' => 'prime256v1',
            'secp256k1' => 'secp256k1',
            'P-384' => 'secp384r1',
            'P-521' => 'secp521r1',
            default => throw new InvalidArgumentException(sprintf('The curve "%s" is not supported.', $curve)),
        };
    }
    private static function p256PublicKey(): string
    {
        return pack('H*', '3059' . '3013' . '0607' . '2a8648ce3d0201' . '0608' . '2a8648ce3d030107' . '0342' . '00');
    }
    private static function p256KPublicKey(): string
    {
        return pack('H*', '3056' . '3010' . '0607' . '2a8648ce3d0201' . '0605' . '2B8104000A' . '0342' . '00');
    }
    private static function p384PublicKey(): string
    {
        return pack('H*', '3076' . '3010' . '0607' . '2a8648ce3d0201' . '0605' . '2b81040022' . '0362' . '00');
    }
    private static function p521PublicKey(): string
    {
        return pack('H*', '30819b' . '3010' . '0607' . '2a8648ce3d0201' . '0605' . '2b81040023' . '038186' . '00');
    }
    private static function p256PrivateKey(JWK $jwk): string
    {
        $d = $jwk->get('d');
        if (!is_string($d)) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        $d = unpack('H*', str_pad(Base64UrlSafe::decodeNoPadding($d), 32, "\x00", STR_PAD_LEFT));
        if (!is_array($d) || !isset($d[1])) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        return pack('H*', '3077' . '020101' . '0420' . $d[1] . 'a00a' . '0608' . '2a8648ce3d030107' . 'a144' . '0342' . '00');
    }
    private static function p256KPrivateKey(JWK $jwk): string
    {
        $d = $jwk->get('d');
        if (!is_string($d)) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        $d = unpack('H*', str_pad(Base64UrlSafe::decodeNoPadding($d), 32, "\x00", STR_PAD_LEFT));
        if (!is_array($d) || !isset($d[1])) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        return pack('H*', '3074' . '020101' . '0420' . $d[1] . 'a007' . '0605' . '2b8104000a' . 'a144' . '0342' . '00');
    }
    private static function p384PrivateKey(JWK $jwk): string
    {
        $d = $jwk->get('d');
        if (!is_string($d)) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        $d = unpack('H*', str_pad(Base64UrlSafe::decodeNoPadding($d), 48, "\x00", STR_PAD_LEFT));
        if (!is_array($d) || !isset($d[1])) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        return pack('H*', '3081a4' . '020101' . '0430' . $d[1] . 'a007' . '0605' . '2b81040022' . 'a164' . '0362' . '00');
    }
    private static function p521PrivateKey(JWK $jwk): string
    {
        $d = $jwk->get('d');
        if (!is_string($d)) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        $d = unpack('H*', str_pad(Base64UrlSafe::decodeNoPadding($d), 66, "\x00", STR_PAD_LEFT));
        if (!is_array($d) || !isset($d[1])) {
            throw new InvalidArgumentException('Unable to get the private key');
        }
        return pack('H*', '3081dc' . '020101' . '0442' . $d[1] . 'a007' . '0605' . '2b81040023' . 'a18189' . '038186' . '00');
    }
    private static function getKey(JWK $jwk): string
    {
        $crv = $jwk->get('crv');
        if (!is_string($crv)) {
            throw new InvalidArgumentException('Unable to get the curve');
        }
        $nistCurveSize = self::getNistCurveSize($crv);
        $length = (int) ceil($nistCurveSize / 8);
        $x = $jwk->get('x');
        if (!is_string($x)) {
            throw new InvalidArgumentException('Unable to get the public key');
        }
        $y = $jwk->get('y');
        if (!is_string($y)) {
            throw new InvalidArgumentException('Unable to get the public key');
        }
        $binX = ltrim(Base64UrlSafe::decodeNoPadding($x), "\x00");
        $binY = ltrim(Base64UrlSafe::decodeNoPadding($y), "\x00");
        return "\x04" . str_pad($binX, $length, "\x00", STR_PAD_LEFT) . str_pad($binY, $length, "\x00", STR_PAD_LEFT);
    }
}