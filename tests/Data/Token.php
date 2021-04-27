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

use Whoa\OAuthServer\Contracts\TokenInterface;

/**
 * @package Whoa\Tests\OAuthServer
 */
class Token implements TokenInterface
{
    /**
     * @var string
     */
    private $clientIdentifier;

    /**
     * @var string|int|null
     */
    private $userIdentifier;

    /**
     * @var string[]
     */
    private $scopeIdentifiers;

    /**
     * @var string|null
     */
    private $tokenValue;

    /**
     * @var string|null
     */
    private $refreshValue;

    /**
     * Token constructor.
     *
     * @param string          $clientIdentifier
     * @param int|string|null $userIdentifier
     * @param string[]        $scopeIdentifiers
     * @param null|string     $tokenValue
     * @param null|string     $refreshValue
     */
    public function __construct(
        string $clientIdentifier,
        $userIdentifier = null,
        array $scopeIdentifiers = [],
        string $tokenValue = null,
        string $refreshValue = null
    )
    {
        $this->clientIdentifier = $clientIdentifier;
        $this->userIdentifier   = $userIdentifier;
        $this->scopeIdentifiers = $scopeIdentifiers;
        $this->tokenValue       = $tokenValue;
        $this->refreshValue     = $refreshValue;
    }


    /**
     * @inheritdoc
     */
    public function getClientIdentifier(): string
    {
        return $this->clientIdentifier;
    }

    /**
     * @inheritdoc
     */
    public function getUserIdentifier()
    {
        return $this->userIdentifier;
    }

    /**
     * @inheritdoc
     */
    public function getScopeIdentifiers(): array
    {
        return $this->scopeIdentifiers;
    }

    /**
     * @inheritdoc
     */
    public function getValue(): ?string
    {
        return $this->tokenValue;
    }

    /**
     * @inheritdoc
     */
    public function getRefreshValue(): ?string
    {
        return $this->refreshValue;
    }
}
