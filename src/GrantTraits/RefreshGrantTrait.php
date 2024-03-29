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

namespace Whoa\OAuthServer\GrantTraits;

use Whoa\OAuthServer\Contracts\ClientInterface;
use Whoa\OAuthServer\Contracts\Integration\RefreshIntegrationInterface;
use Whoa\OAuthServer\Exceptions\OAuthTokenBodyException;
use Psr\Http\Message\ResponseInterface;

use function array_diff;
use function array_key_exists;
use function explode;
use function is_string;

/**
 * @package Whoa\OAuthServer
 * @link    https://tools.ietf.org/html/rfc6749#section-6
 */
trait RefreshGrantTrait
{
    /**
     * @var RefreshIntegrationInterface
     */
    private RefreshIntegrationInterface $refreshIntegration;

    /**
     * @return RefreshIntegrationInterface
     */
    protected function refreshGetIntegration(): RefreshIntegrationInterface
    {
        return $this->refreshIntegration;
    }

    /**
     * @param RefreshIntegrationInterface $refreshIntegration
     * @return void
     */
    public function refreshSetIntegration(RefreshIntegrationInterface $refreshIntegration): void
    {
        $this->refreshIntegration = $refreshIntegration;
    }

    /**
     * @param string[] $parameters
     * @return string|null
     */
    protected function refreshGetValue(array $parameters): ?string
    {
        return $this->refreshReadStringValue($parameters, 'refresh_token');
    }

    /**
     * @param string[] $parameters
     * @return string[]|null
     */
    protected function refreshGetScope(array $parameters): ?array
    {
        $scope = $this->refreshReadStringValue($parameters, 'scope');

        return empty($scope) === false ? explode(' ', $scope) : null;
    }

    /**
     * @param string[] $parameters
     * @param ClientInterface|null $determinedClient
     * @return ResponseInterface
     */
    protected function refreshIssueToken(array $parameters, ?ClientInterface $determinedClient): ResponseInterface
    {
        if (($refreshValue = $this->refreshGetValue($parameters)) === null) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_REQUEST);
        }

        if (($token = $this->refreshGetIntegration()->readTokenByRefreshValue($refreshValue)) === null) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_GRANT);
        }

        $clientIdFromToken = $token->getClientIdentifier();
        if ($determinedClient === null) {
            $isClientFromToken = true;
            $determinedClient = $this->refreshGetIntegration()->readClientByIdentifier($clientIdFromToken);
        } else {
            $isClientFromToken = false;
        }

        // if client didn't provide authentication (but had to) or
        // client associated with the token do not match provided client credentials we throw an exception
        if (($isClientFromToken === true &&
                ($determinedClient->isConfidential() === true || $determinedClient->hasCredentials() === true)) ||
            $determinedClient->getIdentifier() !== $clientIdFromToken
        ) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_CLIENT);
        }

        if ($determinedClient->isRefreshGrantEnabled() === false) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_UNAUTHORIZED_CLIENT);
        }

        $isScopeModified = false;
        $scopeList = null;
        if (($requestedScope = $this->refreshGetScope($parameters)) !== null) {
            // check requested scope is within the current one
            if (empty(array_diff($requestedScope, $token->getScopeIdentifiers())) === false) {
                throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_SCOPE);
            }
            $isScopeModified = true;
            $scopeList = $requestedScope;
        }

        return $this->refreshGetIntegration()->refreshCreateAccessTokenResponse(
            $determinedClient,
            $token,
            $isScopeModified,
            $scopeList,
            $parameters
        );
    }

    /**
     * @param array $parameters
     * @param string $name
     * @return null|string
     */
    private function refreshReadStringValue(array $parameters, string $name): ?string
    {
        return array_key_exists($name, $parameters) === true && is_string($value = $parameters[$name]) === true ?
            $value : null;
    }
}
