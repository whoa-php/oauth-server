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

namespace Whoa\OAuthServer\Contracts;

/**
 * @package Whoa\OAuthServer
 */
interface AuthorizationCodeInterface
{
    /**
     * @return string
     */
    public function getCode(): string;

    /**
     * @return string
     */
    public function getClientIdentifier(): string;

    /**
     * @return string|null
     */
    public function getRedirectUriString(): ?string;

    /**
     * @return string[]
     */
    public function getScopeIdentifiers(): array;

    /**
     * If the scope was modified from original client request.
     *
     * @return bool
     */
    public function isScopeModified(): bool;

    /**
     * If the code has already been used earlier.
     *
     * @return bool
     */
    public function hasBeenUsedEarlier(): bool;
}
