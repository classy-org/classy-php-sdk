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
        if ($assoc) {
            return json_decode($this->getPrevious()->getResponse()->getBody()->getContents(), true);
        }

        return json_decode($this->getPrevious()->getResponse()->getBody()->getContents());
    }

    public function getResponseHeaders()
    {
        return $this->getPrevious()->getResponse()->getHeaders();
    }
}
