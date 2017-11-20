<?php

namespace Graphael;

use Psr\Container\ContainerInterface;

class TypeRegistry
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
