<?php namespace Limoncello\Flute\L10n;

/**
 * Copyright 2015-2018 info@neomerx.com
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

/**
 * @package Limoncello\Flute
 */
interface Messages
{
    /**
     * Namespace for string resources.
     */
    const RESOURCES_NAMESPACE = 'Limoncello.Flute';

    /** Message id */
    const MSG_ERR_INVALID_ELEMENT = 0;

    /** Message id */
    const MSG_ERR_INVALID_PARAMETER = self::MSG_ERR_INVALID_ELEMENT + 1;

    /** Message id */
    const MSG_ERR_INVALID_OPERATION = self::MSG_ERR_INVALID_PARAMETER + 1;

    /** Message id */
    const MSG_ERR_INVALID_ARGUMENT = self::MSG_ERR_INVALID_OPERATION + 1;
}
