<?php

namespace Classy\Tests;


use Classy\Session;

class SessionTest extends TestCase
{
    /**
     * @covers Classy\Session::__construct
     * @covers Classy\Session::set
     */
    public function testConstruct()
    {
        $session = new Session([
            'access_token' => 'abc',
            'refresh_token' => 'def',
            'expires_in' => 3600
        ]);
        $this->assertEquals('abc', $session->getAccessToken());
        $this->assertEquals('def', $session->getRefreshToken());
    }

    /**
     * @covers Classy\Session::serialize
     * @covers Classy\Session::unserialize
     */
    public function testSerialize()
    {
        $session = new Session([
            'access_token' => 'abc',
            'refresh_token' => 'def',
            'expires_in' => 3600
        ]);

        $session2 = unserialize(serialize($session));
        $this->assertEquals($session->getAccessToken(), $session2->getAccessToken());
        $this->assertEquals($session->getRefreshToken(), $session2->getRefreshToken());
    }
}
