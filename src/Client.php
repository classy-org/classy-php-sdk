<?php

namespace Classy;

use Classy\Exceptions\APIResponseException;
use Classy\Exceptions\SDKException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;

class Client
{
    /**
     * @var string
     */
    private $client_id;

    /**
     * @var string
     */
    private $client_secret;

    /**
     * @var string
     */
    private $version;

    /**
     * @var GuzzleClient
     */
    private $httpClient;

    /**
     * @param array $config
     * @throws SDKException
     */
    public function __construct(array $config, $clientClass = GuzzleClient::class)
    {
        $config += [
            'base_uri'       => 'https://api.classy.org',
            'check_ssl_cert' => true
        ];

        $this->httpClient = new GuzzleClient([
            'base_uri' => $config['base_uri'],
            'verify'   => (boolean) $config['check_ssl_cert']
        ]);

        if (!isset($config['version'])) {
            throw new SDKException("You must define the version of Classy API you want to use");
        }
        if (!isset($config['client_id'])) {
            throw new SDKException("client_id is missing");
        }
        if (!isset($config['client_secret'])) {
            throw new SDKException("client_secret is missing");
        }

        $this->version = urlencode($config['version']);
        $this->client_id = $config['client_id'];
        $this->client_secret = $config['client_secret'];
    }

    /**
     * @param $httpClient
     * @codeCoverageIgnore
     */
    public function setHttpClient($httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @return GuzzleClient
     * @codeCoverageIgnore
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @return Session
     */
    public function newAppSession()
    {
        $session = new Session();
        $this->refresh($session);
        return $session;
    }

    /**
     * @param $code
     * @return Session
     */
    public function newMemberSessionFromCode($code)
    {
        $response = $this->request('POST', '/oauth2/auth', null, [
            'form_params' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'code' => $code
            ]
        ]);
        return new Session($response);
    }

    /**
     * @param $username
     * @param $password
     * @return Session|null
     */
    public function newMemberSessionFromCredentials($username, $password)
    {
        try {
            $response = $this->request('POST', '/oauth2/auth', null, [
                'form_params' => [
                    'grant_type'    => 'password',
                    'client_id'     => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'username' => $username,
                    'password' => $password
                ]
            ]);
        } catch (APIResponseException $e) {
            $response = $e->getResponseData();
            if (isset($response->error) && $response->error == 'Invalid user credentials') {
                return null;
            }
            throw $e;
        }
        return new Session($response);
    }

    /**
     * @param string $refresh_token
     * @return Session
     */
    public function newMemberSessionFromRefreshToken($refresh_token)
    {
        $response = $this->request('POST', '/oauth2/auth', null, [
            'form_params' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $refresh_token
            ]
        ]);
        return new Session($response);
    }

    /**
     * @param Session $session
     */
    public function refresh(Session $session)
    {
        if (!is_null($session->getRefreshToken())) {
            $response = $this->request('POST', '/oauth2/auth', null, [
                'form_params' => [
                    'grant_type'    => 'refresh_token',
                    'client_id'     => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'refresh_token' => $session->getRefreshToken()
                ]
            ]);
        } else {
            $response = $this->request('POST', '/oauth2/auth', null, [
                'form_params' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->client_id,
                    'client_secret' => $this->client_secret,
                ]
            ]);
        }
        $session->set($response);
    }

    /**
     * @param $endpoint
     * @param Session|null $session
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get($endpoint, Session $session = null)
    {
        $endpoint = $this->applyVersion($this->version, $endpoint);
        return $this->request('GET', $endpoint, $session);
    }

    /**
     * @param $endpoint
     * @param Session|null $session
     * @param array $payload
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function post($endpoint, Session $session = null, $payload = [])
    {
        $endpoint = $this->applyVersion($this->version, $endpoint);
        return $this->request('POST', $endpoint, $session, [
            'json' => $payload
        ]);
    }

    /**
     * @param $endpoint
     * @param Session|null $session
     * @param array $payload
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function put($endpoint, Session $session = null, $payload = [])
    {
        $endpoint = $this->applyVersion($this->version, $endpoint);
        return $this->request('PUT', $endpoint, $session, [
            'json' => $payload
        ]);
    }

    /**
     * @param $endpoint
     * @param Session|null $session
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function delete($endpoint, Session $session = null)
    {
        $endpoint = $this->applyVersion($this->version, $endpoint);
        return $this->request('DELETE', $endpoint, $session);
    }

    /**
     * @param $verb
     * @param $endpoint
     * @param Session|null $session
     * @param array $options
     * @return array
     */
    public function request($verb, $endpoint, Session $session = null, $options = [])
    {
        if (!is_null($session)) {
            if ($session->expired()) {
                $this->refresh($session);
            }
            if (!isset($options['headers'])) {
                $options['headers'] = [];
            }
            $options['headers']['Authorization'] = "Bearer {$session->getAccessToken()}";
        }

        try {
            $content = $this->httpClient
                ->request($verb, $endpoint, $options)
                ->getBody()
                ->getContents();
        } catch (BadResponseException $e) {
            throw new APIResponseException($e->getMessage(), $e->getCode(), $e);
        }

        return json_decode($content);
    }

    private function applyVersion($version, $endpoint)
    {
        $version = trim($version, "/ \t\n\r\0\x0B");
        $endpoint = trim($endpoint, "/ \t\n\r\0\x0B");
        return "/$version/$endpoint";
    }
}
