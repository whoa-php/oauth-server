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
use Whoa\OAuthServer\Exceptions\OAuthCodeRedirectException;
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
class CodeServerTest extends ServerTestCase
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
    public const REDIRECT_URI_1 = SampleServer::TEST_CLIENT_REDIRECT_URI;

    /**
     * Client redirect URI.
     */
    public const REDIRECT_URI_2 = 'http://example.foo/redirect2?param2=value2';

    /**
     * Test successful auth with redirect URI.
     * @throws Exception
     */
    public function testSuccessfulCodeIssue()
    {
        $server = new SampleServer($this->createRepositoryMock($this->createClient()));
        $state = '123';

        $request = $this->createAuthRequest(
            static::CLIENT_ID,
            static::REDIRECT_URI_1,
            static::CLIENT_DEFAULT_SCOPE,
            $state
        );
        $response = $server->getCreateAuthorization($request);

        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $this->getExpectedRedirectCode($state));
    }

    /**
     * Test successful auth with redirect URI.
     * @link https://github.com/limoncello-php/framework/issues/49
     * @throws Exception
     */
    public function testSuccessfulCodeIssueEmptyScope()
    {
        $client = $this->createClient()->useDefaultScopesOnEmptyRequest();
        $server = new SampleServer($this->createRepositoryMock($client));
        $state = '123';

        $request = $this->createAuthRequest(
            static::CLIENT_ID,
            static::REDIRECT_URI_1,
            '', // <-- empty scope
            $state
        );
        $response = $server->getCreateAuthorization($request);

        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $this->getExpectedRedirectCode($state));
    }

    /**
     * Test successful auth without redirect URI.
     * @throws Exception
     */
    public function testSuccessfulCodeIssueWithoutRedirectUri()
    {
        // as we expect redirect URI to be taken from client the client should have 1 redirect URI
        $client = $this->createClient();
        $client->setRedirectionUris([static::REDIRECT_URI_1]);

        $server = new SampleServer($this->createRepositoryMock($client));
        $server->setInputUriOptional();

        $request = $this->createAuthRequest(
            static::CLIENT_ID,
            null,
            static::CLIENT_DEFAULT_SCOPE
        );
        $response = $server->getCreateAuthorization($request);

        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $this->getExpectedRedirectCode());
    }

    /**
     * Test failed auth without redirect URI.
     * @throws Exception
     */
    public function testFailedCodeIssueWithoutRedirectUri1()
    {
        // as we expect redirect URI to be taken from client the client should have 1 redirect URI
        $client = $this->createClient();
        $client->setRedirectionUris([static::REDIRECT_URI_1]);

        $server = new SampleServer($this->createRepositoryMock($client));
        $server->setInputUriMandatory();

        $request = $this->createAuthRequest(
            static::CLIENT_ID,
            null,
            static::CLIENT_DEFAULT_SCOPE
        );
        $response = $server->getCreateAuthorization($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * Test failed auth without redirect URI.
     * @throws Exception
     */
    public function testFailedCodeIssueWithoutRedirectUri2()
    {
        // make sure client has more than 1 redirect URI so it cannot be determined which one to use automatically
        $client = $this->createClient();
        $this->assertGreaterThan(1, count($client->getRedirectUriStrings()));

        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createAuthRequest(
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
    public function testFailedCodeIssueDueToTooLongStateParameter()
    {
        $client = $this->createClient();
        $client->setRedirectionUris([static::REDIRECT_URI_1]);
        $server = new SampleServer($this->createRepositoryMock($client));

        // limit max state length so it will cause an error
        $state = '123';
        $server->setMaxStateLength(1);

        $request = $this->createAuthRequest(
            static::CLIENT_ID,
            static::REDIRECT_URI_1,
            static::CLIENT_DEFAULT_SCOPE,
            $state
        );
        $response = $server->getCreateAuthorization($request);

        $expectedFragments = $this
            ->getExpectedRedirectCodeErrorFragments(OAuthCodeRedirectException::ERROR_INVALID_REQUEST, $state);
        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $expectedFragments);
    }

    /**
     * Test failed auth due to invalid scope.
     * @throws Exception
     */
    public function testFailedCodeIssueDueInvalidScope()
    {
        $client = $this->createClient();
        $client->setRedirectionUris([static::REDIRECT_URI_1]);
        $server = new SampleServer($this->createRepositoryMock($client));
        $state = '123';

        $request = $this->createAuthRequest(
            static::CLIENT_ID,
            static::REDIRECT_URI_1,
            static::CLIENT_DEFAULT_SCOPE . ' and something else',
            $state
        );
        $response = $server->getCreateAuthorization($request);

        $expectedFragments = $this
            ->getExpectedRedirectCodeErrorFragments(OAuthCodeRedirectException::ERROR_INVALID_SCOPE, $state);
        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $expectedFragments);
    }

    /**
     * Test failed auth due to client does not allow code authorization grant.
     * @throws Exception
     */
    public function testFailedCodeIssueDueCodeAuthorizationGrantIsNotAllowed()
    {
        $client = $this->createClient()
            ->setRedirectionUris([static::REDIRECT_URI_1])
            ->disableCodeAuthorization();

        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createAuthRequest(
            static::CLIENT_ID,
            static::REDIRECT_URI_1,
            static::CLIENT_DEFAULT_SCOPE
        );
        $response = $server->getCreateAuthorization($request);

        $expectedFragments = $this
            ->getExpectedRedirectCodeErrorFragments(OAuthCodeRedirectException::ERROR_UNAUTHORIZED_CLIENT);
        $this->validateRedirectResponse($response, static::REDIRECT_URI_1, $expectedFragments);
    }

    /**
     * Test successful token issue with redirect URI.
     * @throws Exception
     */
    public function testSuccessfulTokenIssue()
    {
        $server = new SampleServer($this->createRepositoryMock($this->createClient()));

        $request = $this->createTokenRequest(
            static::CLIENT_ID,
            SampleServer::TEST_AUTH_CODE,
            static::REDIRECT_URI_1
        );
        $response = $server->postCreateToken($request);

        $this->validateBodyResponse($response, 200, $this->getExpectedBodyToken());
    }

    /**
     * Test successful token issue with redirect URI and client authentication.
     * @throws Exception
     */
    public function testSuccessfulTokenIssueWithClientAuthentication()
    {
        $identifier = static::CLIENT_ID;
        $password = static::CLIENT_PASSWORD;

        $client = $this->createClient()
            ->setCredentials(password_hash(static::CLIENT_PASSWORD, PASSWORD_DEFAULT));

        $this->assertEquals($identifier, $client->getIdentifier());

        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createTokenRequest(
            static::CLIENT_ID,
            SampleServer::TEST_AUTH_CODE,
            static::REDIRECT_URI_1,
            ['Authorization' => 'Basic ' . base64_encode("$identifier:$password")]
        );
        $response = $server->postCreateToken($request);

        $this->validateBodyResponse($response, 200, $this->getExpectedBodyToken());
    }

    /**
     * Test failed token issue with client id not matching client authentication.
     * @throws Exception
     */
    public function testFailedTokenIssueWithClientAuthentication()
    {
        $identifier = static::CLIENT_ID;
        $password = static::CLIENT_PASSWORD;

        $client = $this->createClient()
            ->setCredentials(password_hash(static::CLIENT_PASSWORD, PASSWORD_DEFAULT));

        $this->assertEquals($identifier, $client->getIdentifier());

        $server = new SampleServer($this->createNoMethodsRepositoryMock());

        $request = $this->createTokenRequest(
            static::CLIENT_ID . '_xxx', // <-- invalid client id here
            SampleServer::TEST_AUTH_CODE,
            static::REDIRECT_URI_1,
            ['Authorization' => 'Basic ' . base64_encode("$identifier:$password")]
        );
        $response = $server->postCreateToken($request);

        $expectedFragments = $this
            ->getExpectedBodyTokenError(OAuthTokenBodyException::ERROR_INVALID_REQUEST);
        $this->validateBodyResponse($response, 400, $expectedFragments);
    }

    /**
     * Test failed token issue due to client denied code auth.
     * @throws Exception
     */
    public function testFailedTokenIssueDueToClientDeniedCodeAuth()
    {
        $client = $this->createClient()->disableCodeAuthorization();
        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createTokenRequest(
            static::CLIENT_ID,
            SampleServer::TEST_AUTH_CODE,
            static::REDIRECT_URI_1
        );
        $response = $server->postCreateToken($request);

        $expectedFragments = $this
            ->getExpectedBodyTokenError(OAuthTokenBodyException::ERROR_UNAUTHORIZED_CLIENT);
        $this->validateBodyResponse($response, 400, $expectedFragments);
    }

    /**
     * Test failed token issue due to invalid auth code.
     * @throws Exception
     */
    public function testFailedTokenIssueDueToInvalidAuthCode()
    {
        $server = new SampleServer($this->createRepositoryMock($this->createClient()));

        $request = $this->createTokenRequest(
            static::CLIENT_ID,
            SampleServer::TEST_AUTH_CODE . '_xxx', // <-- invalid auth code
            static::REDIRECT_URI_1
        );
        $response = $server->postCreateToken($request);

        $expectedFragments = $this
            ->getExpectedBodyTokenError(OAuthTokenBodyException::ERROR_INVALID_GRANT);
        $this->validateBodyResponse($response, 400, $expectedFragments);
    }

    /**
     * Test failed token issue due to used earlier auth code.
     * @throws Exception
     */
    public function testFailedTokenIssueDueToUsedEarlierAuthCode()
    {
        $server = new SampleServer($this->createRepositoryMock($this->createClient()));

        $request = $this->createTokenRequest(
            static::CLIENT_ID,
            SampleServer::TEST_USED_AUTH_CODE, // <-- 'used earlier' auth code
            static::REDIRECT_URI_1
        );
        $response = $server->postCreateToken($request);

        $expectedFragments = $this
            ->getExpectedBodyTokenError(OAuthTokenBodyException::ERROR_INVALID_GRANT);
        $this->validateBodyResponse($response, 400, $expectedFragments);
    }

    /**
     * Test failed token issue due to the auth token was issued to another client.
     * @throws Exception
     */
    public function testFailedTokenIssueDueToAuthTokenIssuedToAnotherClient()
    {
        $identifier = static::CLIENT_ID . '_modified';
        $password = static::CLIENT_PASSWORD;

        $client = $this->createClient()
            ->setIdentifier($identifier)
            ->setCredentials(password_hash(static::CLIENT_PASSWORD, PASSWORD_DEFAULT));

        $this->assertEquals($identifier, $client->getIdentifier());

        $server = new SampleServer($this->createRepositoryMock($client));

        $request = $this->createTokenRequest(
            $identifier,
            SampleServer::TEST_AUTH_CODE, // <-- we know that this token is assigned to other client id
            static::REDIRECT_URI_1,
            ['Authorization' => 'Basic ' . base64_encode("$identifier:$password")]
        );
        $response = $server->postCreateToken($request);

        $expectedFragments = $this
            ->getExpectedBodyTokenError(OAuthTokenBodyException::ERROR_UNAUTHORIZED_CLIENT);
        $this->validateBodyResponse($response, 400, $expectedFragments);
    }

    /**
     * Test failed token issue due to absent redirect URI.
     * @throws Exception
     */
    public function testFailedTokenIssueDueAbsentRedirectUri()
    {
        $server = new SampleServer($this->createRepositoryMock($this->createClient()));

        $request = $this->createTokenRequest(
            static::CLIENT_ID,
            SampleServer::TEST_AUTH_CODE,
            null // we know that redirect URI was used for getting auth code
        );
        $response = $server->postCreateToken($request);

        $expectedFragments = $this
            ->getExpectedBodyTokenError(OAuthTokenBodyException::ERROR_INVALID_GRANT);
        $this->validateBodyResponse($response, 400, $expectedFragments);
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
     * @return RepositoryInterface
     */
    private function createNoMethodsRepositoryMock(): RepositoryInterface
    {
        /** @var Mock $mock */
        /** @var RepositoryInterface $mock */

        return Mockery::mock(RepositoryInterface::class);
    }

    /**
     * @return Client
     */
    private function createClient(): Client
    {
        return (new Client(static::CLIENT_ID))
            ->enableCodeAuthorization()
            ->setScopes(explode(' ', static::CLIENT_DEFAULT_SCOPE))
            ->setRedirectionUris([static::REDIRECT_URI_1, static::REDIRECT_URI_2]);
    }

    /**
     * @param string $clientId
     * @param string|null $redirectUri
     * @param string|null $scope
     * @param string|null $state
     * @return ServerRequestInterface
     */
    private function createAuthRequest(
        string $clientId,
        string $redirectUri = null,
        string $scope = null,
        string $state = null
    ): ServerRequestInterface {
        return $this->createServerRequest(null, [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'state' => $state,
        ]);
    }

    /**
     * @param string $clientId
     * @param string $code
     * @param string|null $redirectUri
     * @param array|null $headers
     * @return ServerRequestInterface
     */
    private function createTokenRequest(
        string $clientId,
        string $code,
        string $redirectUri = null,
        array $headers = null
    ): ServerRequestInterface {
        return $this->createServerRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
        ], null, $headers);
    }
}
