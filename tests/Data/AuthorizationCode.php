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

use Whoa\OAuthServer\Contracts\AuthorizationCodeInterface;

/**
 * @package Whoa\OAuth
 */
class AuthorizationCode implements AuthorizationCodeInterface
{
    /**
     * @var string
     */
    private string $code;

    /**
     * @var string
     */
    private string $clientIdentifier;

    /**
     * @var string
     */
    private string $redirectUri;

    /**
     * @var string[]
     */
    private array $scope;

    /**
     * @var bool
     */
    private bool $isScopeModified = false;

    /**
     * @var bool
     */
    private bool $isUsedEarlier = false;

    /**
     * @param string $code
     * @param string $clientIdentifier
     * @param string|null $redirectUri
     * @param string[] $scope
     */
    public function __construct(string $code, string $clientIdentifier, string $redirectUri = null, array $scope = [])
    {
        $this->setCode($code)->setClientIdentifier($clientIdentifier)->setRedirectUri($redirectUri)->setScope($scope);
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return AuthorizationCode
     */
    public function setCode(string $code): AuthorizationCode
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getClientIdentifier(): string
    {
        return $this->clientIdentifier;
    }

    /**
     * @param string $clientIdentifier
     * @return AuthorizationCode
     */
    public function setClientIdentifier(string $clientIdentifier): AuthorizationCode
    {
        $this->clientIdentifier = $clientIdentifier;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getRedirectUriString(): ?string
    {
        return $this->redirectUri;
    }

    /**
     * @param string|null $redirectUri
     * @return AuthorizationCode
     */
    public function setRedirectUri(string $redirectUri = null): AuthorizationCode
    {
        $this->redirectUri = $redirectUri;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getScopeIdentifiers(): array
    {
        return $this->scope;
    }

    /**
     * @param string[] $scope
     * @return AuthorizationCode
     */
    public function setScope(array $scope): AuthorizationCode
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isScopeModified(): bool
    {
        return $this->isScopeModified;
    }

    /**
     * @return AuthorizationCode
     */
    public function setScopeModified(): AuthorizationCode
    {
        $this->isScopeModified = true;

        return $this;
    }

    /**
     * @return AuthorizationCode
     */
    public function setScopeUnmodified(): AuthorizationCode
    {
        $this->isScopeModified = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hasBeenUsedEarlier(): bool
    {
        return $this->isUsedEarlier;
    }

    /**
     * @return AuthorizationCode
     */
    public function setHasBeenUsedEarlier(): AuthorizationCode
    {
        $this->isUsedEarlier = true;

        return $this;
    }

    /**
     * @return AuthorizationCode
     */
    public function setHasNotBeenUsedEarlier(): AuthorizationCode
    {
        $this->isUsedEarlier = false;

        return $this;
    }
}
