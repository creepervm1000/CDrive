<?php

namespace OCA\Notifications\Vendor\Http\Client\Promise;

use OCA\Notifications\Vendor\Http\Client\Exception;
use OCA\Notifications\Vendor\Http\Promise\Promise;
use OCA\Notifications\Vendor\Psr\Http\Message\ResponseInterface;
final class HttpFulfilledPromise implements Promise
{
    /**
     * @var ResponseInterface
     */
    private $response;
    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null)
    {
        if (null === $onFulfilled) {
            return $this;
        }
        try {
            return new self($onFulfilled($this->response));
        } catch (Exception $e) {
            return new HttpRejectedPromise($e);
        }
    }
    public function getState()
    {
        return Promise::FULFILLED;
    }
    public function wait($unwrap = true)
    {
        if ($unwrap) {
            return $this->response;
        }
    }
}