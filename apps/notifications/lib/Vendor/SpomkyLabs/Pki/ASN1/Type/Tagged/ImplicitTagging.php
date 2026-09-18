<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Type\Tagged;

use OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Component\Identifier;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Feature\ElementBase;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Type\UnspecifiedType;
/**
 * Interface for classes providing implicit tagging.
 */
interface ImplicitTagging extends ElementBase
{
    /**
     * Get implicitly tagged wrapped element.
     *
     * @param int $tag Tag of the element
     * @param int $class Expected type class of the element
     */
    public function implicit(int $tag, int $class = Identifier::CLASS_UNIVERSAL): UnspecifiedType;
}