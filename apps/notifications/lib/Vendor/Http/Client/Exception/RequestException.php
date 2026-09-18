<?php

namespace OCA\Notifications\Vendor\Http\Client\Exception;

use OCA\Notifications\Vendor\Psr\Http\Client\RequestExceptionInterface as PsrRequestException;
use OCA\Notifications\Vendor\Psr\Http\Message\RequestInterface;
/**
 * Exception for when a request failed, providing access to the failed request.
 *
 * This could be due to an invalid request, or one of the extending exceptions
 * for network errors or HTTP error responses.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class RequestException extends TransferException implements PsrRequestException
{
    use RequestAwareTrait;
    /**
     * @param string $message
     */
    public function __construct($message, RequestInterface $request, ?\Exception $previous = null)
    {
        $this->setRequest($request);
        parent::__construct($message, 0, $previous);
    }
}