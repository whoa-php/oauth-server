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
interface GrantTypes
{
    /**
     * Authorization code grant type.
     * @link https://tools.ietf.org/html/rfc6749#section-1.3.1
     * @link https://tools.ietf.org/html/rfc6749#section-4.1.3
     */
    public const AUTHORIZATION_CODE = 'authorization_code';

    /**
     * Resource owner password credentials grant type.
     * @link https://tools.ietf.org/html/rfc6749#section-1.3.3
     * @link https://tools.ietf.org/html/rfc6749#section-4.3.2
     */
    public const RESOURCE_OWNER_PASSWORD_CREDENTIALS = 'password';

    /**
     * Client credentials grant type.
     * @link https://tools.ietf.org/html/rfc6749#section-1.3.4
     * @link https://tools.ietf.org/html/rfc6749#section-4.4.2
     */
    public const CLIENT_CREDENTIALS = 'client_credentials';

    /**
     * Refresh token.
     * @link https://tools.ietf.org/html/rfc6749#section-6
     */
    public const REFRESH_TOKEN = 'refresh_token';
}
