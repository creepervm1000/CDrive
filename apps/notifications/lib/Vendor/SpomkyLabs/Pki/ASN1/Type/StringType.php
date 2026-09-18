<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Type;

use OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Feature\ElementBase;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Feature\Stringable;
/**
 * Interface to mark types that correspond to ASN.1 specification's character strings. That being all simple strings and
 * time types.
 */
interface StringType extends ElementBase, Stringable
{
}