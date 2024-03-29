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
use Whoa\OAuthServer\Contracts\Integration\CodeIntegrationInterface;
use Whoa\OAuthServer\Exceptions\OAuthCodeRedirectException;
use Whoa\OAuthServer\Exceptions\OAuthTokenBodyException;
use Psr\Http\Message\ResponseInterface;

use function array_key_exists;
use function explode;
use function is_string;
use function strlen;

/**
 * @package Whoa\OAuthServer
 * @link    https://tools.ietf.org/html/rfc6749#section-1.3
 */
trait CodeGrantTrait
{
    /**
     * @var CodeIntegrationInterface
     */
    private CodeIntegrationInterface $codeIntegration;

    /**
     * @param CodeIntegrationInterface $integration
     * @return void
     */
    public function codeSetIntegration(CodeIntegrationInterface $integration): void
    {
        $this->codeIntegration = $integration;
    }

    /**
     * @return CodeIntegrationInterface
     */
    protected function codeGetIntegration(): CodeIntegrationInterface
    {
        return $this->codeIntegration;
    }

    /**
     * @param string[] $parameters
     * @return string|null
     */
    protected function codeGetClientId(array $parameters): ?string
    {
        return $this->codeReadStringValue($parameters, 'client_id');
    }

    /**
     * @param string[] $parameters
     * @return string|null
     */
    protected function codeGetRedirectUri(array $parameters): ?string
    {
        return $this->codeReadStringValue($parameters, 'redirect_uri');
    }

    /**
     * @param string[] $parameters
     * @return string[]|null
     */
    protected function codeGetScope(array $parameters): ?array
    {
        $scope = $this->codeReadStringValue($parameters, 'scope');

        return empty($scope) === false ? explode(' ', $scope) : null;
    }

    /**
     * @param string[] $parameters
     * @return string|null
     */
    protected function codeGetState(array $parameters): ?string
    {
        return $this->codeReadStringValue($parameters, 'state');
    }

    /**
     * @param string[] $parameters
     * @return string|null
     */
    protected function codeGetCode(array $parameters): ?string
    {
        return $this->codeReadStringValue($parameters, 'code');
    }

    /**
     * @param string[] $parameters
     * @param ClientInterface $client
     * @param string|null $redirectUri
     * @param int|null $maxStateLength
     * @return ResponseInterface
     */
    protected function codeAskResourceOwnerForApproval(
        array $parameters,
        ClientInterface $client,
        string $redirectUri = null,
        int $maxStateLength = null
    ): ResponseInterface {
        $state = $this->codeGetState($parameters);
        if ($maxStateLength !== null && strlen($state) > $maxStateLength) {
            throw new OAuthCodeRedirectException(
                OAuthCodeRedirectException::ERROR_INVALID_REQUEST,
                $redirectUri,
                $state
            );
        }

        if ($client->isCodeGrantEnabled() === false) {
            throw new OAuthCodeRedirectException(
                OAuthCodeRedirectException::ERROR_UNAUTHORIZED_CLIENT,
                $redirectUri,
                $state
            );
        }

        $scope = $this->codeGetScope($parameters);
        [$isScopeValid, $scopeList, $isScopeModified] =
            $this->codeGetIntegration()->codeValidateScope($client, $scope);
        if ($isScopeValid === false) {
            throw new OAuthCodeRedirectException(
                OAuthCodeRedirectException::ERROR_INVALID_SCOPE,
                $redirectUri,
                $state
            );
        }

        return $this->codeGetIntegration()->codeCreateAskResourceOwnerForApprovalResponse(
            $client,
            $redirectUri,
            $isScopeModified,
            $scopeList,
            $state
        );
    }

    /**
     * @param string[] $parameters
     * @param ClientInterface|null $determinedClient
     * @return ResponseInterface
     */
    protected function codeIssueToken(array $parameters, ClientInterface $determinedClient = null): ResponseInterface
    {
        // client_id is required @link https://tools.ietf.org/html/rfc6749#section-4.1.3
        if ($determinedClient === null || $determinedClient->isCodeGrantEnabled() === false) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_UNAUTHORIZED_CLIENT);
        }

        if (($codeValue = $this->codeGetCode($parameters)) === null ||
            ($code = $this->codeGetIntegration()->codeReadAuthenticationCode($codeValue)) === null
        ) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_GRANT);
        }

        if ($code->hasBeenUsedEarlier() === true) {
            $this->codeGetIntegration()->codeRevokeTokens($code);
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_GRANT);
        }

        if ($code->getClientIdentifier() !== $determinedClient->getIdentifier()) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_UNAUTHORIZED_CLIENT);
        }

        if ($code->getRedirectUriString() !== null) {
            // REQUIRED, if the "redirect_uri" parameter was included in the authorization request
            if (($redirectUri = $this->codeGetRedirectUri($parameters)) === null ||
                $redirectUri !== $code->getRedirectUriString()
            ) {
                throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_GRANT);
            }
        }

        return $this->codeGetIntegration()->codeCreateAccessTokenResponse($code, $parameters);
    }

    /**
     * @param array $parameters
     * @param string $name
     * @return null|string
     */
    private function codeReadStringValue(array $parameters, string $name): ?string
    {
        return array_key_exists($name, $parameters) === true && is_string($value = $parameters[$name]) === true ?
            $value : null;
    }
}
