<?php

namespace Classy;

use Classy\Exceptions\SDKException;
use DateTime;

Class Session implements \Serializable
{
    /**
     * @var string
     */
    private $access_token;

    /**
     * @var string
     */
    private $refresh_token;

    /**
     * @var int
     */
    private $expires_in;

    /**
     * @var int
     */
    private $expires_at;


    public function __construct($attributes = null)
    {
        if (!is_null($attributes)) {
            $this->set($attributes);
        }
    }

    public function set($attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }
        $this->expires_at = time() + $this->expires_in;
    }

    /**
     * @return string
     * @codeCoverageIgnore
     */
    public function getAccessToken()
    {
        return $this->access_token;
    }

    /**
     * @return string
     * @codeCoverageIgnore
     */
    public function getRefreshToken()
    {
        return $this->refresh_token;
    }

    /**
     * @return bool
     */
    public function expired()
    {
        // Adding some margin. Session will be marked as expired 60 seconds before it really expires.
        return time() + 60 > $this->expires_at;
    }

    /**
     * @return DateTime
     */
    public function expires_at()
    {
        $result = new DateTime();
        $result->setTimestamp($this->expires_at);
        return $result;
    }


    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize(get_object_vars($this));
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        $values = unserialize($serialized);
        foreach ($values as $key => $value) {
            $this->$key = $value;
        }
    }
}
