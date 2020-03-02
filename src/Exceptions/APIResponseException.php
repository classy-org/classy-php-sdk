<?php

namespace Classy\Exceptions;

use GuzzleHttp\Exception\BadResponseException;

class APIResponseException extends \Exception
{
    public function __construct($message = "", $code = 0, BadResponseException $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public function getResponseData($assoc = false)
    {
        return json_decode($this->getPrevious()->getResponse()->getBody()->getContents(), $assoc);
    }

    public function getResponseHeaders()
    {
        return $this->getPrevious()->getResponse()->getHeaders();
    }
}
