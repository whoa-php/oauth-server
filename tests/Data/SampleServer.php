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

namespace Whoa\Tests\OAuthServer\Data;

use Whoa\OAuthServer\BaseAuthorizationServer;
use Whoa\OAuthServer\Contracts\AuthorizationCodeInterface;
use Whoa\OAuthServer\Contracts\ClientInterface;
use Whoa\OAuthServer\Contracts\GrantTypes;
use Whoa\OAuthServer\Contracts\ResponseTypes;
use Whoa\OAuthServer\Contracts\TokenInterface;
use Whoa\OAuthServer\Exceptions\OAuthRedirectException;
use Whoa\OAuthServer\Exceptions\OAuthTokenBodyException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\RedirectResponse;
use Zend\Diactoros\Uri;

use function assert;
use function array_filter;
use function array_key_exists;
use function count;
use function explode;
use function implode;
use function is_string;
use function substr;

/**
 * @package Whoa\Tests\OAuthServer
 */
class SampleServer extends BaseAuthorizationServer
{
    /** Test data */
    public const TEST_USER_NAME = 'john@dow.foo';

    /** Test data */
    public const TEST_PASSWORD = 'password';

    /** Test data */
    public const TEST_AUTH_CODE = '5ebe2294ecd0e0f08eab7690d2a6ee69';

    /** Test data */
    public const TEST_USED_AUTH_CODE = 'c7f7a58918e790e28827a4e272462914';

    /** Test data */
    public const TEST_TOKEN = '4142011b2689166ce7760644a0b5f8d0';

    /** Test data */
    public const TEST_REFRESH_TOKEN = 'e57941dbe1246fe97a4ffc16e85b5df9';

    /** Test data */
    public const TEST_TOKEN_NEW = '4142011b2689166ce7760644a0b5f8d0_new';

    /** Test data */
    public const TEST_REFRESH_TOKEN_NEW = 'e57941dbe1246fe97a4ffc16e85b5df9_new';

    /** Test data */
    public const TEST_SCOPES = ['scope1', 'scope2'];

    /** Test data */
    public const TEST_TOKEN_TYPE = 'bearer';

    /** Test data */
    public const TEST_TOKEN_EXPIRES_IN = 3600;

    /** Test data */
    public const TEST_UNSUPPORTED_GRANT_TYPE_ERROR_URI = 'http://example.com/error123';

    /** Test data */
    public const TEST_ACCESS_DENIED_ERROR_URI = 'http://example.com/error456';

    /** Test data */
    public const TEST_CLIENT_ID = 'some_client_id';

    /** Test data */
    public const TEST_CLIENT_REDIRECT_URI = 'https://client.foo/redirect';

    /**
     * @var RepositoryInterface
     */
    private RepositoryInterface $repository;

    /**
     * @param RepositoryInterface $repository
     */
    public function __construct(RepositoryInterface $repository)
    {
        parent::__construct();

        $this->setRepository($repository);
    }

    /**
     * @inheritdoc
     */
    protected function createAuthorization(array $parameters): ResponseInterface
    {
        try {
            $client = null;
            $redirectUri = null;
            $responseType = $this->getResponseType($parameters);
            switch ($responseType) {
                case ResponseTypes::AUTHORIZATION_CODE:
                    [$client, $redirectUri] = $this->getValidClientAndRedirectUri(
                        $this->codeGetClientId($parameters),
                        $this->codeGetRedirectUri($parameters)
                    );
                    $response = $client === null || $redirectUri === null ?
                        $this->createInvalidClientAndRedirectUriErrorResponse() :
                        $this->codeAskResourceOwnerForApproval(
                            $parameters,
                            $client,
                            $redirectUri,
                            $this->getMaxStateLength()
                        );
                    break;
                case ResponseTypes::IMPLICIT:
                    [$client, $redirectUri] = $this->getValidClientAndRedirectUri(
                        $this->implicitGetClientId($parameters),
                        $this->implicitGetRedirectUri($parameters)
                    );
                    $response = $client === null || $redirectUri === null ?
                        $this->createInvalidClientAndRedirectUriErrorResponse() :
                        $this->implicitAskResourceOwnerForApproval(
                            $parameters,
                            $client,
                            $redirectUri,
                            $this->getMaxStateLength()
                        );
                    break;
                default:
                    throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_UNSUPPORTED_GRANT_TYPE);
            }
        } catch (OAuthRedirectException $exception) {
            $response = $this->createRedirectErrorResponse($exception);
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function postCreateToken(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $parameters = $request->getParsedBody();
            $determinedClient = $this->determineClient($request, $parameters);

            switch ($this->getGrantType($parameters)) {
                case GrantTypes::AUTHORIZATION_CODE:
                    $response = $this->codeIssueToken($parameters, $determinedClient);
                    break;
                case GrantTypes::RESOURCE_OWNER_PASSWORD_CREDENTIALS:
                    $response = $this->passIssueToken($parameters, $determinedClient);
                    break;
                case GrantTypes::CLIENT_CREDENTIALS:
                    if ($determinedClient === null) {
                        throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_CLIENT);
                    }
                    $response = $this->clientIssueToken($parameters, $determinedClient);
                    break;
                case GrantTypes::REFRESH_TOKEN:
                    $response = $this->refreshIssueToken($parameters, $determinedClient);
                    break;
                default:
                    throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_UNSUPPORTED_GRANT_TYPE);
            }
        } catch (OAuthTokenBodyException $exception) {
            $response = $this->createBodyErrorResponse($exception);
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function codeCreateAskResourceOwnerForApprovalResponse(
        ClientInterface $client,
        string $redirectUri = null,
        bool $isScopeModified = false,
        array $scopeList = null,
        string $state = null,
        array $extraParameters = []
    ): ResponseInterface {
        // This method should return some kind of HTML response with a list of scopes asking resource owner for
        // approval. If the scope is approved the controller should create and save authentication code and
        // make redirect with this code to client.
        //
        // As this logic is app specific it's not a part of server code.
        //
        // For demonstration purposes and simplicity we skip 'approval' step and issue the code right away.

        $code = (new AuthorizationCode(static::TEST_AUTH_CODE, $client->getIdentifier(), $redirectUri, $scopeList));
        $isScopeModified === true ? $code->setScopeModified() : $code->setScopeUnmodified();

        return $this->createRedirectCodeResponse($client, $code, $state);
    }

    /**
     * @inheritdoc
     */
    public function codeReadAuthenticationCode(string $code): ?AuthorizationCodeInterface
    {
        // As out server do not actually implement storing auth codes we will emulate it.
        $result = null;

        if ($code === static::TEST_AUTH_CODE) {
            $result = new AuthorizationCode($code, static::TEST_CLIENT_ID, static::TEST_CLIENT_REDIRECT_URI);
        } elseif ($code === static::TEST_USED_AUTH_CODE) {
            $result = new AuthorizationCode($code, static::TEST_CLIENT_ID, static::TEST_CLIENT_REDIRECT_URI);
            $result->setHasBeenUsedEarlier();
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function codeCreateAccessTokenResponse(
        AuthorizationCodeInterface $code,
        array $extraParameters = []
    ): ResponseInterface {
        $token = static::TEST_TOKEN;
        $type = static::TEST_TOKEN_TYPE;
        $expiresIn = static::TEST_TOKEN_EXPIRES_IN;
        $refreshToken = static::TEST_REFRESH_TOKEN;
        $scopeList = $code->isScopeModified() === true ? $code->getScopeIdentifiers() : null;

        // let's pretend we've saved the token parameters for the user

        return $this->createBodyTokenResponse($token, $type, $expiresIn, $refreshToken, $scopeList);
    }

    /**
     * @inheritdoc
     */
    public function codeRevokeTokens(AuthorizationCodeInterface $code): void
    {
        // pretend we actually revoke all related tokens
    }

    /**
     * @inheritdoc
     */
    public function implicitCreateAskResourceOwnerForApprovalResponse(
        ClientInterface $client,
        string $redirectUri = null,
        bool $isScopeModified = false,
        array $scopeList = null,
        string $state = null,
        array $extraParameters = []
    ): ResponseInterface {
        // This method should return some kind of HTML response with a list of scopes asking resource owner for
        // approval. If the scope is approved the controller should create and save token and return it.
        // As this logic is app specific it's not a part of server code.
        //
        // For demonstration purposes and simplicity we skip 'approval' step and issue the token right away.

        $redirectUri = $this->selectValidRedirectUri($client, $redirectUri);

        $token = static::TEST_TOKEN;
        $type = static::TEST_TOKEN_TYPE;
        $expiresIn = static::TEST_TOKEN_EXPIRES_IN;
        $scopes = $isScopeModified === true ? $scopeList : null;

        return $this->createRedirectTokenResponse($redirectUri, $token, $type, $expiresIn, $scopes, $state);
    }

    /**
     * @inheritdoc
     */
    public function passValidateCredentialsAndCreateAccessTokenResponse(
        string $userName,
        string $password,
        ClientInterface $client = null,
        bool $isScopeModified = false,
        array $scope = null,
        array $extraParameters = []
    ): ResponseInterface {
        // let's pretend we've made a query to our database and checked the credentials
        $areCredentialsValid = $userName === static::TEST_USER_NAME && $password === static::TEST_PASSWORD;

        if ($areCredentialsValid === false) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_GRANT);
        }

        $token = static::TEST_TOKEN;
        $type = static::TEST_TOKEN_TYPE;
        $expiresIn = static::TEST_TOKEN_EXPIRES_IN;
        $refreshToken = static::TEST_REFRESH_TOKEN;

        // let's pretend we've saved the token parameters for the user

        return $this->createBodyTokenResponse($token, $type, $expiresIn, $refreshToken);
    }

    /**
     * @inheritdoc
     */
    public function passReadDefaultClient(): ClientInterface
    {
        return $this->getRepository()->readDefaultClient();
    }

    /**
     * @inheritdoc
     */
    public function clientCreateAccessTokenResponse(
        ClientInterface $client,
        bool $isScopeModified,
        array $scope = null,
        array $extraParameters = []
    ): ResponseInterface {
        $token = static::TEST_TOKEN;
        $type = static::TEST_TOKEN_TYPE;
        $expiresIn = static::TEST_TOKEN_EXPIRES_IN;

        // let's pretend we've saved the token parameters for the client

        return $this->createBodyTokenResponse($token, $type, $expiresIn);
    }

    /**
     * @inheritdoc
     */
    public function refreshCreateAccessTokenResponse(
        ClientInterface $client,
        TokenInterface $token,
        bool $isScopeModified,
        array $scope = null,
        array $extraParameters = []
    ): ResponseInterface {
        $token = static::TEST_TOKEN_NEW;
        $type = static::TEST_TOKEN_TYPE;
        $expiresIn = static::TEST_TOKEN_EXPIRES_IN;
        $refresh = static::TEST_REFRESH_TOKEN_NEW;

        // let's pretend we've updated and saved token parameters and send a new set to client

        return $this->createBodyTokenResponse($token, $type, $expiresIn, $refresh, $scope);
    }

    /**
     * @inheritdoc
     */
    public function readTokenByRefreshValue(string $refreshValue): ?TokenInterface
    {
        return $this->getRepository()->readTokenByRefreshValue($refreshValue);
    }

    /**
     * @inheritdoc
     */
    public function readClientByIdentifier(string $clientIdentifier): ?ClientInterface
    {
        return $this->getRepository()->readClient($clientIdentifier);
    }

    /**
     * @param ServerRequestInterface $request
     * @param array $parameters
     * @return ClientInterface|null
     * It does not authenticate clients in all cases. It's allowed to provide only client identifier without
     * client credentials. In that case if client is not confidential and no credentials have been issued to the client
     * it would be OK.
     */
    private function determineClient(ServerRequestInterface $request, array $parameters): ?ClientInterface
    {
        // As an example let's implement `Basic` client authorization

        // A client may use Basic authentication.
        //
        // Or
        //
        // A client MAY use the "client_id" request parameter to identify itself
        // when sending requests to the token endpoint.
        // @link https://tools.ietf.org/html/rfc6749#section-3.2.1

        // try to parse `Authorization` header for client ID and credentials
        $clientId = null;
        $clientCredentials = null;
        $errorHeaders = ['WWW-Authenticate' => 'Basic realm="OAuth"'];
        if (empty($headerArray = $request->getHeader('Authorization')) === false) {
            $errorCode = OAuthTokenBodyException::ERROR_INVALID_CLIENT;
            if (empty($authHeader = $headerArray[0]) === true ||
                ($tokenPos = strpos($authHeader, 'Basic ')) === false ||
                $tokenPos !== 0 ||
                ($authValue = substr($authHeader, 6)) === '' ||
                $authValue === false ||
                ($decodedValue = base64_decode($authValue, true)) === false ||
                ($idAndCredentials = explode(':', $decodedValue, 2)) === false
            ) {
                throw new OAuthTokenBodyException($errorCode, null, 401, $errorHeaders);
            }
            $headerPartsCount = count($idAndCredentials);
            switch ($headerPartsCount) {
                case 1:
                    $clientId = $idAndCredentials[0];
                    break;
                case 2:
                    $clientId = $idAndCredentials[0];
                    $clientCredentials = $idAndCredentials[1];
                    break;
                default:
                    throw new OAuthTokenBodyException($errorCode, null, 401, $errorHeaders);
            }
        }

        // check if client ID was specified in parameters it should match
        if (array_key_exists('client_id', $parameters) === true &&
            is_string($value = $parameters['client_id']) === true
        ) {
            if ($clientId !== null && $clientId !== $value) {
                $errorCode = OAuthTokenBodyException::ERROR_INVALID_REQUEST;
                throw new OAuthTokenBodyException($errorCode, null, 400, $errorHeaders);
            }

            $clientId = $value;
            unset($value);
        }

        // when we are here we know if any client ID and credentials were given

        $client = null;
        if ($clientId !== null) {
            $errorCode = OAuthTokenBodyException::ERROR_INVALID_CLIENT;
            assert(is_string($clientId));
            if (($client = $this->getRepository()->readClient($clientId)) === null) {
                throw new OAuthTokenBodyException($errorCode, null, 401, $errorHeaders);
            }

            // check credentials
            if ($clientCredentials !== null) {
                // we got the password
                if (password_verify($clientCredentials, $client->getCredentials()) === false) {
                    throw new OAuthTokenBodyException($errorCode, null, 401, $errorHeaders);
                }
            } elseif ($client->isConfidential() === true || $client->hasCredentials() === true) {
                // no password provided
                throw new OAuthTokenBodyException($errorCode, null, 401, $errorHeaders);
            }
        }

        return $client;
    }

    /**
     * @param string|null $clientId
     * @param string|null $redirectFromQuery
     * @return array [client|null, uri|null]
     */
    private function getValidClientAndRedirectUri(string $clientId = null, string $redirectFromQuery = null): array
    {
        $client = null;
        $validRedirectUri = null;

        if ($clientId !== null && ($client = $this->getRepository()->readClient($clientId)) !== null) {
            $validRedirectUri = $this->selectValidRedirectUri($client, $redirectFromQuery);
        }

        return [$client, $validRedirectUri];
    }

    /**
     * @param ClientInterface $client
     * @param AuthorizationCodeInterface $code
     * @param string|null $state
     * @return ResponseInterface
     */
    private function createRedirectCodeResponse(
        ClientInterface $client,
        AuthorizationCodeInterface $code,
        string $state = null
    ): ResponseInterface {
        // for authorization code format @link https://tools.ietf.org/html/rfc6749#section-4.1.2
        $parameters = $this->filterNulls([
            'code' => $code->getCode(),
            'state' => $state,
        ]);

        $fragment = $this->encodeAsXWwwFormUrlencoded($parameters);

        $redirectUri = $this->selectValidRedirectUri($client, $code->getRedirectUriString());
        return new RedirectResponse((new Uri($redirectUri))->withFragment($fragment));
    }

    /**
     * @param string $redirectUri
     * @param string $token
     * @param string $type
     * @param int $expiresIn
     * @param string[]|null $scopeIdentifiers
     * @param string|null $state
     * @return ResponseInterface
     */
    private function createRedirectTokenResponse(
        string $redirectUri,
        string $token,
        string $type,
        int $expiresIn,
        array $scopeIdentifiers = null,
        string $state = null
    ): ResponseInterface {
        // for access token format @link https://tools.ietf.org/html/rfc6749#section-4.2.2
        //
        // Also from #4.2.2
        //
        // Developers should note that some user-agents do not support the inclusion of a fragment component in the
        // HTTP "Location" response header field.  Such clients will require using other methods for redirecting
        // the client than a 3xx redirection response -- for example, returning an HTML page that includes a
        // 'continue' button with an action linked to the redirection URI.

        $scope = $scopeIdentifiers === null ? null : implode(' ', $scopeIdentifiers);
        $parameters = $this->filterNulls([
            'access_token' => $token,
            'token_type' => $type,
            'expires_in' => $expiresIn,
            'scope' => $scope,
            'state' => $state,
        ]);

        $fragment = $this->encodeAsXWwwFormUrlencoded($parameters);

        return new RedirectResponse((new Uri($redirectUri))->withFragment($fragment));
    }

    /**
     * @param string $token
     * @param string $type
     * @param int $expiresIn
     * @param string|null $refreshToken
     * @param string[]|null $scopeList
     * @return ResponseInterface
     */
    private function createBodyTokenResponse(
        string $token,
        string $type,
        int $expiresIn,
        string $refreshToken = null,
        array $scopeList = null
    ): ResponseInterface {
        // for access token format @link https://tools.ietf.org/html/rfc6749#section-5.1
        $scope = $scopeList === null ? null : implode(' ', $scopeList);
        $parameters = $this->filterNulls([
            'access_token' => $token,
            'token_type' => $type,
            'expires_in' => $expiresIn,
            'refresh_token' => $refreshToken,
            'scope' => $scope,
        ]);

        return new JsonResponse($parameters, 200, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache'
        ]);
    }

    /**
     * @param OAuthRedirectException $exception
     * @return ResponseInterface
     */
    private function createRedirectErrorResponse(OAuthRedirectException $exception): ResponseInterface
    {
        $parameters = $this->filterNulls([
            'error' => $exception->getErrorCode(),
            'error_description' => $exception->getErrorDescription(),
            'error_uri' => $exception->getErrorUri(),
            'state' => $exception->getState(),
        ]);
        $fragment = $this->encodeAsXWwwFormUrlencoded($parameters);
        $uri = (new Uri($exception->getRedirectUri()))->withFragment($fragment);

        return new RedirectResponse($uri, 302, $exception->getHttpHeaders());
    }

    /**
     * @param OAuthTokenBodyException $exception
     * @return ResponseInterface
     * @link https://tools.ietf.org/html/rfc6749#section-5.2
     */
    private function createBodyErrorResponse(OAuthTokenBodyException $exception): ResponseInterface
    {
        $array = $this->filterNulls([
            'error' => $exception->getErrorCode(),
            'error_description' => $exception->getErrorDescription(),
            'error_uri' => $this->getBodyErrorUri($exception),
        ]);

        return new JsonResponse($array, $exception->getHttpCode(), $exception->getHttpHeaders());
    }

    /**
     * @return ResponseInterface
     */
    private function createInvalidClientAndRedirectUriErrorResponse(): ResponseInterface
    {
        return new HtmlResponse('Combination of client identifier and redirect URI is invalid. <br>', 400);
    }

    /**
     * @return RepositoryInterface
     */
    private function getRepository(): RepositoryInterface
    {
        return $this->repository;
    }

    /**
     * @param RepositoryInterface $repository
     * @return SampleServer
     */
    private function setRepository(RepositoryInterface $repository): SampleServer
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * @param array $array
     * @return array
     */
    private function filterNulls(array $array): array
    {
        return array_filter($array, function ($value) {
            return $value !== null;
        });
    }

    /**
     * @param OAuthTokenBodyException $exception
     * @return null|string
     */
    protected function getBodyErrorUri(OAuthTokenBodyException $exception): ?string
    {
        $uri = $exception->getErrorUri();

        if ($uri === null) {
            if ($exception->getErrorCode() == OAuthTokenBodyException::ERROR_UNSUPPORTED_GRANT_TYPE) {
                $uri = static::TEST_UNSUPPORTED_GRANT_TYPE_ERROR_URI;
            }
        }

        return $uri;
    }

    /**
     * @param OAuthRedirectException $exception
     * @return null|string
     */
    protected function getRedirectErrorUri(OAuthRedirectException $exception): ?string
    {
        $uri = $exception->getErrorUri();

        if ($uri === null) {
            if ($exception->getErrorCode() == OAuthRedirectException::ERROR_ACCESS_DENIED) {
                $uri = static::TEST_ACCESS_DENIED_ERROR_URI;
            }
        }

        return $uri;
    }
}
