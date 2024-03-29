<?php

/**
 * Copyright 2015-2020 info@neomerx.com
 * Modification Copyright 2021-2022 info@whoaphp.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Whoa\Tests\OAuthServer;

use Exception;
use Whoa\OAuthServer\Contracts\ClientInterface;
use Whoa\OAuthServer\Exceptions\OAuthTokenBodyException;
use Whoa\Tests\OAuthServer\Data\Client;
use Whoa\Tests\OAuthServer\Data\RepositoryInterface;
use Whoa\Tests\OAuthServer\Data\SampleServer;
use Mockery;
use Mockery\Mock;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @package Whoa\Tests\OAuthServer
 */
class ClientServerTest extends ServerTestCase
{
    /**
     * Client id.
     */
    public const CLIENT_ID = 'some_client_id';

    /**
     * Client id.
     */
    public const CLIENT_PASSWORD = 'secret';

    /**
     * Client default scope.
     */
    public const CLIENT_DEFAULT_SCOPE = 'some scope';

    /**
     * Client redirect URI.
     */
    public const REDIRECT_URI = SampleServer::TEST_CLIENT_REDIRECT_URI;

    /**
     * Test successful token issue without scope.
     * @throws Exception
     */
    public function testSuccessfulTokenIssueWithoutScope()
    {
        // As we do not send scope we should instruct to use client's default
        $client = $this->createClient()
            ->useDefaultScopesOnEmptyRequest();

        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createTokenRequest();
        $response = $server->postCreateToken($request);

        $this->validateBodyResponse($response, 200, $this->getExpectedBodyTokenNoRefresh());
    }

    /**
     * Test successful token issue with scope.
     * @throws Exception
     */
    public function testSuccessfulTokenIssueWithScope()
    {
        $client = $this->createClient();
        $server = new SampleServer($this->createRepositoryMock($client));

        // let's use only a part of scope for variety
        $scope = explode(' ', static::CLIENT_DEFAULT_SCOPE)[1];
        $request = $this->createTokenRequest($scope);
        $response = $server->postCreateToken($request);

        $this->validateBodyResponse($response, 200, $this->getExpectedBodyTokenNoRefresh());
    }

    /**
     * Test client credentials grant is disabled for the client.
     * @throws Exception
     */
    public function testGrantTypeDisabled()
    {
        $client = $this->createClient()
            ->disableClientGrant();

        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createTokenRequest(static::CLIENT_DEFAULT_SCOPE);
        $response = $server->postCreateToken($request);

        $errorCode = OAuthTokenBodyException::ERROR_UNAUTHORIZED_CLIENT;
        $this->validateBodyResponse($response, 400, $this->getExpectedBodyTokenError($errorCode));
    }

    /**
     * Test invalid scope.
     * @throws Exception
     */
    public function testInvalidScope()
    {
        $client = $this->createClient();
        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createTokenRequest(static::CLIENT_DEFAULT_SCOPE . ' something else');
        $response = $server->postCreateToken($request);

        $errorCode = OAuthTokenBodyException::ERROR_INVALID_SCOPE;
        $this->validateBodyResponse($response, 400, $this->getExpectedBodyTokenError($errorCode));
    }

    /**
     * @return Client
     */
    private function createClient(): Client
    {
        return (new Client(static::CLIENT_ID))
            ->enableClientGrant()
            ->setCredentials(password_hash(static::CLIENT_PASSWORD, PASSWORD_DEFAULT))
            ->setScopes(explode(' ', static::CLIENT_DEFAULT_SCOPE))
            ->setRedirectionUris([static::REDIRECT_URI]);
    }

    /**
     * @param ClientInterface $client
     * @return RepositoryInterface
     */
    private function createRepositoryMock(ClientInterface $client): RepositoryInterface
    {
        /** @var Mock $mock */
        $mock = Mockery::mock(RepositoryInterface::class);
        $mock->shouldReceive('readClient')->once()->with($client->getIdentifier())->andReturn($client);

        /** @var RepositoryInterface $mock */

        return $mock;
    }

    /**
     * @param string|null $scope
     * @param array $headers
     * @return ServerRequestInterface
     */
    private function createTokenRequest(
        string $scope = null,
        array $headers = []
    ): ServerRequestInterface {
        $identifier = static::CLIENT_ID;
        $password = static::CLIENT_PASSWORD;
        return $this->createServerRequest([
            'grant_type' => 'client_credentials',
            'scope' => $scope,
        ], null, $headers + ['Authorization' => 'Basic ' . base64_encode("$identifier:$password")]);
    }
}
