<?php

namespace Graphael;

use Psr\Container\ContainerInterface;

class ContainerTypeRegistry implements TypeRegistryInterface
{
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function get($className)
    {
        return $this->container->get($className);
    }
}
