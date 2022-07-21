<?php

/**
 * Copyright 2015-2019 info@neomerx.com
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

namespace Whoa\Core\Contracts;

/**
 * @package Whoa\Core
 */
interface CoreDataInterface
{
    /** Settings key for router parameters */
    public const KEY_ROUTER_PARAMS = 0;

    /** Settings key for router internal data generator */
    public const KEY_ROUTER_PARAMS__GENERATOR = 0;

    /** Settings key for router dispatcher */
    public const KEY_ROUTER_PARAMS__DISPATCHER = self::KEY_ROUTER_PARAMS__GENERATOR + 1;

    /** Settings key for routing data */
    public const KEY_ROUTES_DATA = self::KEY_ROUTER_PARAMS + 1;

    /** Settings key for routing data */
    public const KEY_GLOBAL_CONTAINER_CONFIGURATORS = self::KEY_ROUTES_DATA + 1;

    /** Settings key for routing data */
    public const KEY_GLOBAL_MIDDLEWARE = self::KEY_GLOBAL_CONTAINER_CONFIGURATORS + 1;

    /** Special key which could be used by developers to safely add their own keys */
    public const KEY_LAST = self::KEY_GLOBAL_MIDDLEWARE;
}
