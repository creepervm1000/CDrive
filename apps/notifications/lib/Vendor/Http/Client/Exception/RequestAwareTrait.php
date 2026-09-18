<?php

namespace OCA\Notifications\Vendor\Http\Client\Exception;

use OCA\Notifications\Vendor\Psr\Http\Message\RequestInterface;
trait RequestAwareTrait
{
    /**
     * @var RequestInterface
     */
    private $request;
    private function setRequest(RequestInterface $request)
    {
        $this->request = $request;
    }
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}