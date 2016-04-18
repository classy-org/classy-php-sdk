<?php

namespace Classy\Tests;

use Classy\Client;
use Mockery;
use PHPUnit_Framework_TestCase;

class TestCase extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    protected $guzzleMock;

    /**
     * @var \Classy\Client
     */
    protected $client;

    public function setUp()
    {
        $this->guzzleMock = Mockery::mock(\GuzzleHttp\Client::class);
        $this->client = new Client([
            'client_id' => '123',
            'client_secret' => '456',
            'version' => '2.0'
        ]);
        $this->client->setHttpClient($this->guzzleMock);
        parent::setUp();
    }

}
