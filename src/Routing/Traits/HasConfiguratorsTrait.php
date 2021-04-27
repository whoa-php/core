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

namespace Whoa\Core\Routing\Traits;

use LogicException;
use Whoa\Contracts\Container\ContainerInterface as WhaoContainerInterface;
use function array_merge;

/**
 * @package Whoa\Core
 *
 * @method string getCallableToCacheMessage();
 */
trait HasConfiguratorsTrait
{
    /**
     * @var callable[]
     */
    private $configurators = [];

    /**
     * @param callable[] $configurators
     *
     * @return self
     */
    public function setConfigurators(array $configurators): self
    {
        foreach ($configurators as $configurator) {
            $isValid = $this->checkPublicStaticCallable($configurator, [WhaoContainerInterface::class]);
            if ($isValid === false) {
                throw new LogicException($this->getCallableToCacheMessage());
            }
        }

        $this->configurators = $configurators;

        return $this;
    }

    /**
     * @param callable[] $configurators
     *
     * @return self
     */
    public function addConfigurators(array $configurators): self
    {
        return $this->setConfigurators(array_merge($this->configurators, $configurators));
    }
}
