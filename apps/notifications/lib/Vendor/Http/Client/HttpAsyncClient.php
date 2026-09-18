<?php

namespace OCA\Notifications\Vendor\Http\Client;

use OCA\Notifications\Vendor\Http\Promise\Promise;
use OCA\Notifications\Vendor\Psr\Http\Message\RequestInterface;
/**
 * Sends a PSR-7 Request in an asynchronous way by returning a Promise.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
interface HttpAsyncClient
{
    /**
     * Sends a PSR-7 request in an asynchronous way.
     *
     * Exceptions related to processing the request are available from the returned Promise.
     *
     * @return Promise resolves a PSR-7 Response or fails with an OCA\Notifications\Vendor\Http\Client\Exception
     *
     * @throws \Exception If processing the request is impossible (eg. bad configuration).
     */
    public function sendAsyncRequest(RequestInterface $request);
}