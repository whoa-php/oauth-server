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
use Whoa\OAuthServer\Contracts\Integration\PasswordIntegrationInterface;
use Whoa\OAuthServer\Exceptions\OAuthTokenBodyException;
use Psr\Http\Message\ResponseInterface;

use function array_key_exists;
use function explode;
use function is_string;

/**
 * Implements Resource Owner Password Credentials Grant.
 * @package Whoa\OAuthServer
 * @link    https://tools.ietf.org/html/rfc6749#section-1.3
 * @link    https://tools.ietf.org/html/rfc6749#section-4.3
 */
trait PasswordGrantTrait
{
    /**
     * @var PasswordIntegrationInterface
     */
    private PasswordIntegrationInterface $passIntegration;

    /**
     * @return PasswordIntegrationInterface
     */
    protected function passGetIntegration(): PasswordIntegrationInterface
    {
        return $this->passIntegration;
    }

    /**
     * @param PasswordIntegrationInterface $passIntegration
     * @return void
     */
    public function passSetIntegration(PasswordIntegrationInterface $passIntegration): void
    {
        $this->passIntegration = $passIntegration;
    }

    /**
     * @param string[] $parameters
     * @return string|null
     */
    protected function passGetUserName(array $parameters): ?string
    {
        return $this->passReadStringValue($parameters, 'username');
    }

    /**
     * @param string[] $parameters
     * @return string|null
     */
    protected function passGetPassword(array $parameters): ?string
    {
        return $this->passReadStringValue($parameters, 'password');
    }

    /**
     * @param string[] $parameters
     * @return string[]|null
     */
    protected function passGetScope(array $parameters): ?array
    {
        $scope = $this->passReadStringValue($parameters, 'scope');

        return empty($scope) === false ? explode(' ', $scope) : null;
    }

    /**
     * @param string[] $parameters
     * @param ClientInterface|null $determinedClient
     * @return ResponseInterface
     */
    protected function passIssueToken(array $parameters, ClientInterface $determinedClient = null): ResponseInterface
    {
        // if client is not given we interpret it as a 'default' client should be used
        if ($determinedClient === null) {
            $determinedClient = $this->passGetIntegration()->passReadDefaultClient();
            if ($determinedClient->hasCredentials() === true) {
                throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_UNAUTHORIZED_CLIENT);
            }
        }

        if ($determinedClient !== null && $determinedClient->isPasswordGrantEnabled() === false) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_UNAUTHORIZED_CLIENT);
        }

        $scope = $this->passGetScope($parameters);
        [$isScopeValid, $scopeList, $isScopeModified] =
            $this->passGetIntegration()->passValidateScope($determinedClient, $scope);
        if ($isScopeValid === false) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_SCOPE);
        }

        if (($userName = $this->passGetUserName($parameters)) === null ||
            ($password = $this->passGetPassword($parameters)) === null
        ) {
            throw new OAuthTokenBodyException(OAuthTokenBodyException::ERROR_INVALID_REQUEST);
        }

        return $this->passGetIntegration()->passValidateCredentialsAndCreateAccessTokenResponse(
            $userName,
            $password,
            $determinedClient,
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
    private function passReadStringValue(array $parameters, string $name): ?string
    {
        return array_key_exists($name, $parameters) === true && is_string($value = $parameters[$name]) === true ?
            $value : null;
    }
}
