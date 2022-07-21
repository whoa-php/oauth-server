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

namespace Whoa\OAuthServer;

use Whoa\OAuthServer\Contracts\AuthorizationServerInterface;
use Whoa\OAuthServer\Contracts\ClientInterface;
use Whoa\OAuthServer\Contracts\Integration\ClientIntegrationInterface as CII;
use Whoa\OAuthServer\Contracts\Integration\CodeIntegrationInterface as AII;
use Whoa\OAuthServer\Contracts\Integration\ImplicitIntegrationInterface as III;
use Whoa\OAuthServer\Contracts\Integration\PasswordIntegrationInterface as PII;
use Whoa\OAuthServer\Contracts\Integration\RefreshIntegrationInterface as RII;
use Whoa\OAuthServer\GrantTraits\ClientGrantTrait;
use Whoa\OAuthServer\GrantTraits\CodeGrantTrait;
use Whoa\OAuthServer\GrantTraits\ImplicitGrantTrait;
use Whoa\OAuthServer\GrantTraits\PasswordGrantTrait;
use Whoa\OAuthServer\GrantTraits\RefreshGrantTrait;
use Whoa\OAuthServer\ServerTraits\OAuthServerTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function assert;
use function http_build_query;

/**
 * @package Whoa\OAuthServer
 */
abstract class BaseAuthorizationServer implements AuthorizationServerInterface, AII, III, PII, CII, RII
{
    use ClientGrantTrait;
    use CodeGrantTrait;
    use ImplicitGrantTrait;
    use OAuthServerTrait;
    use PasswordGrantTrait;
    use RefreshGrantTrait;

    /**
     * Implements Authorization Endpoint.
     * @param array $parameters
     * @return ResponseInterface
     * @link https://tools.ietf.org/html/rfc6749#section-3.1
     * @link https://tools.ietf.org/html/rfc6749#section-4.2.1
     */
    abstract protected function createAuthorization(array $parameters): ResponseInterface;

    /**
     * @var null|int
     */
    private ?int $maxStateLength = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->codeSetIntegration($this);
        $this->implicitSetIntegration($this);
        $this->passSetIntegration($this);
        $this->clientSetIntegration($this);
        $this->refreshSetIntegration($this);
    }

    /**
     * @inheritdoc
     */
    public function getCreateAuthorization(ServerRequestInterface $request): ResponseInterface
    {
        return $this->createAuthorization($request->getQueryParams());
    }

    /**
     * @inheritdoc
     */
    public function postCreateAuthorization(ServerRequestInterface $request): ResponseInterface
    {
        return $this->createAuthorization($request->getParsedBody());
    }

    /**
     * @inheritdoc
     */
    public function codeValidateScope(ClientInterface $client, array $scopes = null): array
    {
        return $this->validateScope($client, $scopes);
    }

    /**
     * @inheritdoc
     */
    public function implicitValidateScope(ClientInterface $client, array $scopes = null): array
    {
        return $this->validateScope($client, $scopes);
    }

    /**
     * @inheritdoc
     */
    public function passValidateScope(ClientInterface $client = null, array $scopes = null): array
    {
        return $this->validateScope($client, $scopes);
    }

    /**
     * @inheritdoc
     */
    public function clientValidateScope(ClientInterface $client, array $scopes = null): array
    {
        return $this->validateScope($client, $scopes);
    }

    /**
     * @return int|null
     */
    public function getMaxStateLength(): ?int
    {
        return $this->maxStateLength;
    }

    /**
     * @param int|null $maxStateLength
     * @return self
     */
    public function setMaxStateLength(int $maxStateLength = null): self
    {
        assert($maxStateLength === null || $maxStateLength > 0);

        $this->maxStateLength = $maxStateLength;

        return $this;
    }

    /**
     * @param array $parameters
     * @return string
     */
    protected function encodeAsXWwwFormUrlencoded(array $parameters): string
    {
        return http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
