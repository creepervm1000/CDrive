<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\SpomkyLabs\Pki\X501\ASN1\AttributeValue;

use OCA\Notifications\Vendor\SpomkyLabs\Pki\X501\ASN1\AttributeType;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\X501\ASN1\AttributeValue\Feature\DirectoryString;
/**
 * 'title' attribute value.
 *
 * @see https://www.itu.int/ITU-T/formal-language/itu-t/x/x520/2012/SelectedAttributeTypes.html#SelectedAttributeTypes.title
 */
final class TitleValue extends DirectoryString
{
    public static function create(string $value, int $string_tag = DirectoryString::UTF8): static
    {
        return new static(AttributeType::OID_TITLE, $value, $string_tag);
    }
}