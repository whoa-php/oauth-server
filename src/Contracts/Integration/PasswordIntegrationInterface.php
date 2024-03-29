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

namespace Whoa\OAuthServer\Contracts\Integration;

use Whoa\OAuthServer\Contracts\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Resource owner password integration interface for server.
 * @package Whoa\OAuthServer
 */
interface PasswordIntegrationInterface extends IntegrationInterface
{
    /**
     * @param ClientInterface|null $client
     * @param array|null $scopes
     * @return array [bool $isScopeValid, string[]|null $scopeList, bool $isScopeModified] Scope list `null` for
     *               invalid, string[] otherwise.
     */
    public function passValidateScope(ClientInterface $client = null, array $scopes = null): array;

    /**
     * Validate resource owner credentials and create access token response. On error (e.g invalid credentials)
     * it throws OAuth exception.
     * @param string $userName
     * @param string $password
     * @param ClientInterface|null $client
     * @param bool $isScopeModified
     * @param array|null $scope
     * @param array $extraParameters
     * @return ResponseInterface
     * @link https://tools.ietf.org/html/rfc6749#section-4.1.4
     */
    public function passValidateCredentialsAndCreateAccessTokenResponse(
        string $userName,
        string $password,
        ClientInterface $client = null,
        bool $isScopeModified = false,
        array $scope = null,
        array $extraParameters = []
    ): ResponseInterface;

    /**
     * @return ClientInterface
     */
    public function passReadDefaultClient(): ClientInterface;
}
