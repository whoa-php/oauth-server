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

use Whoa\OAuthServer\Contracts\ClientInterface;

/**
 * @package Whoa\Tests\OAuthServer
 */
class Client implements ClientInterface
{
    /**
     * @var string
     */
    private string $identifier;

    /**
     * @var bool
     */
    private bool $isConfidential = false;

    /**
     * @var string|null
     */
    private ?string $credentials = null;

    /**
     * @var string[]
     */
    private array $redirectionUris = [];

    /**
     * @var string[]
     */
    private array $scopes = [];

    /**
     * @var bool
     */
    private bool $isUseDefaultScope = false;

    /**
     * @var bool
     */
    private bool $isScopeExcessAllowed = false;

    /**
     * @var bool
     */
    private bool $isCodeAuthEnabled = false;

    /**
     * @var bool
     */
    private bool $isImplicitAuthEnabled = false;

    /**
     * @var bool
     */
    private bool $isPasswordGrantEnabled = false;

    /**
     * @var bool
     */
    private bool $isClientGrantEnabled = false;

    /**
     * @var bool
     */
    private bool $isRefreshGrantEnabled = false;

    /**
     * @param string $identifier
     */
    public function __construct(string $identifier)
    {
        $this->setIdentifier($identifier);
    }

    /**
     * @inheritdoc
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     * @return Client
     */
    public function setIdentifier(string $identifier): Client
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isConfidential(): bool
    {
        return $this->isConfidential;
    }

    /**
     * @return Client
     */
    public function setConfidential(): Client
    {
        $this->isConfidential = true;

        return $this;
    }

    /**
     * @return Client
     */
    public function setPublic(): Client
    {
        $this->isConfidential = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hasCredentials(): bool
    {
        return empty($this->getCredentials()) === false;
    }

    /**
     * @param string $credentials
     * @return Client
     */
    public function setCredentials(string $credentials): Client
    {
        $this->credentials = $credentials;

        return $this;
    }

    /**
     * @return Client
     */
    public function clearCredentials(): Client
    {
        $this->credentials = null;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getCredentials(): ?string
    {
        return $this->credentials;
    }

    /**
     * @inheritdoc
     */
    public function getRedirectUriStrings(): array
    {
        return $this->redirectionUris;
    }

    /**
     * @param string[] $redirectionUris
     * @return Client
     */
    public function setRedirectionUris(array $redirectionUris): Client
    {
        $this->redirectionUris = $redirectionUris;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getScopeIdentifiers(): array
    {
        return $this->scopes;
    }

    /**
     * @param string[] $scopes
     * @return Client
     */
    public function setScopes(array $scopes): Client
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isUseDefaultScopesOnEmptyRequest(): bool
    {
        return $this->isUseDefaultScope;
    }

    /**
     * @return Client
     */
    public function useDefaultScopesOnEmptyRequest(): Client
    {
        $this->isUseDefaultScope = true;

        return $this;
    }

    /**
     * @return Client
     */
    public function doNotUseDefaultScopesOnEmptyRequest(): Client
    {
        $this->isUseDefaultScope = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isScopeExcessAllowed(): bool
    {
        return $this->isScopeExcessAllowed;
    }

    /**
     * @return Client
     */
    public function enableScopeExcess(): Client
    {
        $this->isScopeExcessAllowed = true;

        return $this;
    }

    /**
     * @return Client
     */
    public function disableScopeExcess(): Client
    {
        $this->isScopeExcessAllowed = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isCodeGrantEnabled(): bool
    {
        return $this->isCodeAuthEnabled;
    }

    /**
     * @return Client
     */
    public function enableCodeAuthorization(): Client
    {
        $this->isCodeAuthEnabled = true;

        return $this;
    }

    /**
     * @return Client
     */
    public function disableCodeAuthorization(): Client
    {
        $this->isCodeAuthEnabled = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isImplicitGrantEnabled(): bool
    {
        return $this->isImplicitAuthEnabled;
    }

    /**
     * @return Client
     */
    public function enableImplicitGrant(): Client
    {
        $this->isImplicitAuthEnabled = true;

        return $this;
    }

    /**
     * @return Client
     */
    public function disableImplicitGrant(): Client
    {
        $this->isImplicitAuthEnabled = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isPasswordGrantEnabled(): bool
    {
        return $this->isPasswordGrantEnabled;
    }

    /**
     * @return Client
     */
    public function enablePasswordGrant(): Client
    {
        $this->isPasswordGrantEnabled = true;

        return $this;
    }

    /**
     * @return Client
     */
    public function disablePasswordGrant(): Client
    {
        $this->isPasswordGrantEnabled = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isClientGrantEnabled(): bool
    {
        return $this->isClientGrantEnabled;
    }

    /**
     * @return Client
     */
    public function enableClientGrant(): Client
    {
        $this->isClientGrantEnabled = true;

        return $this;
    }

    /**
     * @return Client
     */
    public function disableClientGrant(): Client
    {
        $this->isClientGrantEnabled = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isRefreshGrantEnabled(): bool
    {
        return $this->isRefreshGrantEnabled;
    }

    /**
     * @return Client
     */
    public function enableRefreshGrant(): Client
    {
        $this->isRefreshGrantEnabled = true;

        return $this;
    }

    /**
     * @return Client
     */
    public function disableRefreshGrant(): Client
    {
        $this->isRefreshGrantEnabled = false;

        return $this;
    }
}
