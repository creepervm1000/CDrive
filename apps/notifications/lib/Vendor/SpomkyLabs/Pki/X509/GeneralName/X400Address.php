<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\SpomkyLabs\Pki\X509\GeneralName;

use OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Type\Tagged\ImplicitlyTaggedType;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Type\TaggedType;
use OCA\Notifications\Vendor\SpomkyLabs\Pki\ASN1\Type\UnspecifiedType;
/**
 * Implements *x400Address* CHOICE type of *GeneralName*.
 *
 * Currently acts as a parking object for decoding.
 *
 * @see https://tools.ietf.org/html/rfc5280#section-4.2.1.6
 *
 * @todo Implement ORAddress type
 */
final class X400Address extends GeneralName
{
    protected function __construct(private readonly Sequence $element)
    {
        parent::__construct(self::TAG_X400_ADDRESS);
    }
    public static function create(Sequence $element): self
    {
        return new self($element);
    }
    public static function fromChosenASN1(UnspecifiedType $el): self
    {
        return self::create($el->asSequence());
    }
    public function string(): string
    {
        return bin2hex($this->element->toDER());
    }
    protected function choiceASN1(): TaggedType
    {
        return ImplicitlyTaggedType::create($this->tag, $this->element);
    }
}