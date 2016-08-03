<?php

namespace Classy\Tests;

use Classy\Client;
use Classy\Exceptions\APIResponseException;
use Classy\Exceptions\SDKException;
use Classy\Session;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
use Mockery;
use ReflectionMethod;

class ClientTest extends TestCase
{

    public function constructProvider()
    {
        return [
            [[], "You must define the version of Classy API you want to use"],
            [['version' => '2.0'], "client_id is missing"],
            [['version' => '2.0', 'client_id' => '123'], "client_secret is missing"],
        ];

    }

    /**
     * @dataProvider constructProvider
     * @covers Classy\Client::__construct
     */
    public function testConstructFailure($inputs, $error)
    {
        try {
            new Client($inputs);
            $this->fail("Exception expected");
        } catch (SDKException $e) {
            $this->assertEquals($error, $e->getMessage());
        }
    }

    /**
     * @covers Classy\Client::__construct
     */
    public function testConstructSuccess()
    {
        $client = new Client([
            'version' => '2.0',
            'base_uri' => 'https://classy.org',
            'client_id' => '123',
            'client_secret' => '456'
        ]);
        $this->assertEquals('https://classy.org', $client->getHttpClient()->getConfig('base_uri')->__toString());
    }

    /**
     * @covers Classy\Client::newAppSession
     * @covers Classy\Session::expired
     */
    public function testNewAppSession()
    {
        $this->guzzleMock->shouldReceive('request')
            ->once()
            ->with('POST', '/oauth2/auth', Mockery::on(function($args) {
                return $args['form_params'] === [
                    'grant_type' => 'client_credentials',
                    'client_id' => '123',
                    'client_secret' => '456',
                ];
            }))
            ->andReturn(new Response(200, [], json_encode([
                "access_token" => 'access_token',
                "expires_in" => 3600
            ])));

        $session = $this->client->newAppSession();
        $this->assertInstanceOf(Session::class, $session);
        $this->assertEquals("access_token", $session->getAccessToken());
        $this->assertFalse($session->expired());
    }

    /**
     * @covers Classy\Client::newMemberSessionFromCode
     */
    public function testNewMemberSessionFromCode()
    {
        $this->guzzleMock->shouldReceive('request')
            ->once()
            ->with('POST', '/oauth2/auth', Mockery::on(function($args) {
                return $args['form_params'] === [
                    'grant_type' => 'authorization_code',
                    'client_id' => '123',
                    'client_secret' => '456',
                    'code' => '789'
                ];
            }))
            ->andReturn(new Response(200, [], "{}"));

        $session = $this->client->newMemberSessionFromCode("789");
        $this->assertInstanceOf(Session::class, $session);
    }

    /**
     * @covers Classy\Client::newMemberSessionFromCredentials
     */
    public function testNewMemberSessionFromCredentials()
    {
        $this->guzzleMock->shouldReceive('request')
            ->once()
            ->with('POST', '/oauth2/auth', Mockery::on(function($args) {
                $this->assertEquals([
                    'grant_type' => 'password',
                    'client_id' => '123',
                    'client_secret' => '456',
                    'username' => 'email@domain.tld',
                    'password' => 'pass',
                    'ip' => null,
                    'foo' => 'bar',
                ], $args['form_params']);
                return true;
            }))
            ->andReturn(new Response(200, [], "{}"));

        $session = $this->client->newMemberSessionFromCredentials([
            'username' => 'email@domain.tld',
            'password' => 'pass',
            'foo'      => 'bar',
        ]);
        $this->assertInstanceOf(Session::class, $session);
    }

    /**
     * @covers Classy\Client::newMemberSessionFromRefreshToken
     */
    public function testNewMemberSessionFromRefreshToken()
    {
        $this->guzzleMock->shouldReceive('request')
            ->once()
            ->with('POST', '/oauth2/auth', Mockery::on(function($args) {
                return $args['form_params'] === [
                    'grant_type' => 'refresh_token',
                    'client_id' => '123',
                    'client_secret' => '456',
                    'refresh_token' => 'token',
                    'ip' => null,
                ];
            }))
            ->andReturn(new Response(200, [], "{}"));

        $session = $this->client->newMemberSessionFromRefreshToken("token");
        $this->assertInstanceOf(Session::class, $session);
    }

    /**
     * @covers Classy\Client::refresh
     */
    public function testRefreshAppToken()
    {
        $session = new Session([
            'access_token' => '12345',
            'expires_in' => -100
        ]);
        $this->assertTrue($session->expired());

        $this->guzzleMock->shouldReceive('request')
            ->once()
            ->with('POST', '/oauth2/auth', Mockery::on(function($args) {
                return $args['form_params'] === [
                    'grant_type' => 'client_credentials',
                    'client_id' => '123',
                    'client_secret' => '456',
                ];
            }))
            ->andReturn(new Response(200, [], json_encode([
                "access_token" => '56789',
                "expires_in" => 3600
            ])));

        $this->client->refresh($session);

        $this->assertEquals('56789', $session->getAccessToken());
        $this->assertFalse($session->expired());
    }

    /**
     * @covers Classy\Client::refresh
     */
    public function testRefreshMemberToken()
    {
        $session = new Session([
            'access_token' => '12345',
            'refresh_token' => '55555',
            'expires_in' => 3600
        ]);

        $this->guzzleMock->shouldReceive('request')
            ->once()
            ->with('POST', '/oauth2/auth', Mockery::on(function($args) {
                return $args['form_params'] === [
                    'grant_type' => 'refresh_token',
                    'client_id' => '123',
                    'client_secret' => '456',
                    'refresh_token' => '55555',
                    'ip' => null,
                ];
            }))
            ->andReturn(new Response(200, [], json_encode([
                "access_token" => '56789',
                "refresh_token" => '6666',
                "expires_in" => 3600
            ])));

        $this->client->refresh($session);

        $this->assertEquals('56789', $session->getAccessToken());
        $this->assertEquals('6666', $session->getRefreshToken());
        $this->assertFalse($session->expired());
    }


    public function testRESTVerbsProvider()
    {
        return [
            ['get', null],
            ['delete', null],
            ['post', ['payload' => 'content']],
            ['put', ['payload' => 'content']],
        ];

    }

    /**
     * @dataProvider testRESTVerbsProvider
     * @covers Classy\Client::get
     * @covers Classy\Client::post
     * @covers Classy\Client::delete
     * @covers Classy\Client::put
     */
    public function testRESTVerbs($verb, array $payload = null)
    {
        $clientMock = Mockery::mock(Client::class . "[request]", [[
            'client_id' => '123',
            'client_secret' => '456',
            'version' => '2.0'
        ]]);

        $expectation = $clientMock->shouldReceive('request')->once();
        if (is_null($payload)) {
            $expectation->with(mb_strtoupper($verb), '/2.0/endpoint', null);
            $clientMock->$verb('endpoint');
        } else {
            $expectation->with(mb_strtoupper($verb), '/2.0/endpoint', null, Mockery::on(function($args) {
                return $args['json'] === ['payload' => 'content'];
            }));
            $clientMock->$verb('endpoint', null, $payload);
        }
    }

    /**
     * @covers Classy\Client::request
     */
    public function testRequest()
    {
        $session = new Session([
            'access_token' => 'abcdef',
            'expires_in' => '3600'
        ]);

        $this->guzzleMock->shouldReceive('request')
            ->once()
            ->with(
                'POST',
                '/3.0/endpoint',
                Mockery::on(function($args) {
                    return $args === [
                        'json' => ['payload' => 'content'],
                        'headers' => ['Authorization' => 'Bearer abcdef']
                    ];
                }))
            ->andReturn(new Response(200, [], "{}"));

        $this->client->request('POST', '/3.0/endpoint', $session, ['json' => ['payload' => 'content']]);
    }

    /**
     * @covers Classy\Client::request
     */
    public function testRequestWithExpiredSession()
    {
        $session = new Session([
            'access_token' => 'abcdef',
            'expires_in' => '-1000'
        ]);

        $this->guzzleMock->shouldReceive('request')
            ->once()
            ->with('POST', '/oauth2/auth', Mockery::on(function($args) {
                return $args['form_params'] === [
                    'grant_type' => 'client_credentials',
                    'client_id' => '123',
                    'client_secret' => '456',
                ];
            }))
            ->andReturn(new Response(200, [], json_encode([
                "access_token" => '56789',
                "expires_in" => 3600
            ])));


        $this->guzzleMock->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                '/3.0/endpoint',
                Mockery::on(function($args) {
                    return $args === ['headers' => ['Authorization' => 'Bearer 56789']];
                }))
            ->andReturn(new Response(200, [], "{}"));

        $this->client->request('GET', '/3.0/endpoint', $session);
        $this->assertFalse($session->expired());
    }

    /**
     * @covers Classy\Client::request
     */
    public function testRequestWithDefaultSession()
    {
        $session = new Session([
            'access_token' => 'abcdef',
            'expires_in' => '1000'
        ]);
        $this->client->setDefaultSession($session);

        $this->guzzleMock->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                '/3.0/endpoint',
                Mockery::on(function($args) {
                    return $args === ['headers' => ['Authorization' => 'Bearer abcdef']];
                }))
            ->andReturn(new Response(200, [], "{}"));

        $this->client->request('GET', '/3.0/endpoint');
    }

    /**
     * @covers Classy\Client::applyVersion
     */
    public function testApplyVersion()
    {
        $testSets = [
            ['2.0', 'endpoint'],
            ['2.0 /', '/endpoint'],
            [' /2.0/ ', '/endpoint'],
            ['/2.0 ', '/ endpoint'],
            [' /2.0 ', '/endpoint '],
            ['/2.0/ ', '/endpoint / '],
        ];
        $method = new ReflectionMethod(Client::class, 'applyVersion');
        $method->setAccessible(true);

        foreach ($testSets as $inputs) {
            $result = $method->invoke($this->client, $inputs[0], $inputs[1]);
            $this->assertEquals('/2.0/endpoint', $result);
        }
    }

    /**
     * @covers Classy\Client::request
     * @covers Classy\Exceptions\APIResponseException
     */
    public function testErrorHandling()
    {
        $client = new Client([
            'version' => '2.0',
            'client_id' => 'aze',
            'client_secret' => 'aze'
        ]);
        try {
            $client->newAppSession();
            $this->fail('Exception expected');
        } catch (APIResponseException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('invalid_request', $e->getResponseData()->error);
            $this->assertEquals('application/json; charset=utf-8', $e->getResponseHeaders()['Content-Type'][0]);
        }
    }
}
