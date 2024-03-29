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
use Whoa\OAuthServer\Exceptions\OAuthTokenRedirectException;
use Whoa\Tests\OAuthServer\Data\Client;
use Whoa\Tests\OAuthServer\Data\RepositoryInterface;
use Whoa\Tests\OAuthServer\Data\SampleServer;
use Mockery;
use Mockery\Mock;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @package Whoa\Tests\OAuthServer
 */
class ImplicitServerTest extends ServerTestCase
{
    /**
     * Client id.
     */
    public const CLIENT_ID = 'some_client_id';

    /**
     * Client default scope.
     */
    public const CLIENT_DEFAULT_SCOPE = 'some scope';

    /**
     * Client redirect URI.
     */
    public const REDIRECT_URI_1 = SampleServer::TEST_CLIENT_REDIRECT_URI;

    /**
     * Client redirect URI.
     */
    public const REDIRECT_URI_2 = 'http://example.foo/redirect2?param2=value2';

    /**
     * Test successful auth with redirect URI (POST method).
     *
     * @throws Exception
     */
    public function testSuccessfulTokenIssue()
    {
        $server = new SampleServer($this->createRepositoryMock($this->createClient()));
        $state = '123';

        $request = $this->createPostAuthRequest(
            static::CLIENT_ID,
            static::REDIRECT_URI_1,
            static::CLIENT_DEFAULT_SCOPE,
            $state
        );
        $response = $server->postCreateAuthorization($request);

        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $this->getExpectedRedirectToken($state));
    }

    /**
     * Test successful auth with redirect URI (POST method).
     * @link https://github.com/limoncello-php/framework/issues/49
     *
     * @throws Exception
     */
    public function testSuccessfulTokenIssueEmptyScope()
    {
        $client = $this->createClient()->useDefaultScopesOnEmptyRequest();
        $server = new SampleServer($this->createRepositoryMock($client));
        $state = '123';

        $request = $this->createPostAuthRequest(
            static::CLIENT_ID,
            static::REDIRECT_URI_1,
            '', // <-- empty scope
            $state
        );
        $response = $server->postCreateAuthorization($request);

        $this->validateRedirectResponse(
            $response,
            static::REDIRECT_URI_1,
            $this->getExpectedRedirectToken($state, static::CLIENT_DEFAULT_SCOPE)
        );
    }

    /**
     * Test successful auth without redirect URI (GET method).
     * @throws Exception
     */
    public function testSuccessfulTokenIssueWithoutRedirectUri()
    {
        // as we expect redirect URI to be taken from client the client should have 1 redirect URI
        $client = $this->createClient();
        $client->setRedirectionUris([static::REDIRECT_URI_1]);

        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createGetAuthRequest(
            static::CLIENT_ID,
            null,
            static::CLIENT_DEFAULT_SCOPE
        );
        $response = $server->getCreateAuthorization($request);

        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $this->getExpectedRedirectToken());
    }

    /**
     * Test failed auth without redirect URI.
     * @throws Exception
     */
    public function testFailedTokenIssueWithoutRedirectUri()
    {
        // make sure client has more than 1 redirect URI so it cannot be determined which one to use automatically
        $client = $this->createClient();
        $this->assertGreaterThan(1, count($client->getRedirectUriStrings()));

        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createGetAuthRequest(
            static::CLIENT_ID,
            null,
            static::CLIENT_DEFAULT_SCOPE
        );
        $response = $server->getCreateAuthorization($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * Test failed auth due to too long `state` parameter.
     * @throws Exception
     */
    public function testFailedTokenIssueDueToTooLongStateParameter()
    {
        $client = $this->createClient();
        $client->setRedirectionUris([static::REDIRECT_URI_1]);
        $server = new SampleServer($this->createRepositoryMock($client));

        // limit max state length so it will cause an error
        $state = '123';
        $server->setMaxStateLength(1);

        $request = $this->createGetAuthRequest(
            static::CLIENT_ID,
            static::REDIRECT_URI_1,
            static::CLIENT_DEFAULT_SCOPE,
            $state
        );
        $response = $server->getCreateAuthorization($request);

        $fragments = $this
            ->getExpectedRedirectTokenErrorFragments(OAuthTokenRedirectException::ERROR_INVALID_REQUEST, $state);
        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $fragments);
    }

    /**
     * Test failed auth due to invalid scope.
     * @throws Exception
     */
    public function testFailedTokenIssueDueInvalidScope()
    {
        $client = $this->createClient();
        $client->setRedirectionUris([static::REDIRECT_URI_1]);
        $server = new SampleServer($this->createRepositoryMock($client));
        $state = '123';

        $request = $this->createGetAuthRequest(
            static::CLIENT_ID,
            static::REDIRECT_URI_1,
            static::CLIENT_DEFAULT_SCOPE . ' and something else',
            $state
        );
        $response = $server->getCreateAuthorization($request);

        $expectedFragments = $this
            ->getExpectedRedirectTokenErrorFragments(OAuthTokenRedirectException::ERROR_INVALID_SCOPE, $state);
        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $expectedFragments);
    }

    /**
     * Test failed auth due to client does not allow implicit authorization grant.
     * @throws Exception
     */
    public function testFailedTokenIssueDueImplicitGrantIsNotAllowed()
    {
        $client = $this->createClient()
            ->setRedirectionUris([static::REDIRECT_URI_1])
            ->disableImplicitGrant();

        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createGetAuthRequest(
            static::CLIENT_ID,
            static::REDIRECT_URI_1,
            static::CLIENT_DEFAULT_SCOPE
        );
        $response = $server->getCreateAuthorization($request);

        $expectedFragments = $this
            ->getExpectedRedirectTokenErrorFragments(OAuthTokenRedirectException::ERROR_UNAUTHORIZED_CLIENT);
        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $expectedFragments);
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
     * @return Client
     */
    private function createClient(): Client
    {
        $client = (new Client(static::CLIENT_ID))
            ->enableImplicitGrant()
            ->setScopes(explode(' ', static::CLIENT_DEFAULT_SCOPE))
            ->setRedirectionUris([static::REDIRECT_URI_1, static::REDIRECT_URI_2]);

        return $client;
    }

    /**
     * @param string $clientId
     * @param string|null $redirectUri
     * @param string|null $scope
     * @param string|null $state
     * @return ServerRequestInterface
     */
    private function createGetAuthRequest(
        string $clientId,
        string $redirectUri = null,
        string $scope = null,
        string $state = null
    ): ServerRequestInterface {
        return $this->createServerRequest(null, [
            'response_type' => 'token',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'state' => $state,
        ]);
    }

    /**
     * @param string $clientId
     * @param string|null $redirectUri
     * @param string|null $scope
     * @param string|null $state
     * @return ServerRequestInterface
     */
    private function createPostAuthRequest(
        string $clientId,
        string $redirectUri = null,
        string $scope = null,
        string $state = null
    ): ServerRequestInterface {
        return $this->createServerRequest([
            'response_type' => 'token',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'state' => $state,
        ]);
    }
}
