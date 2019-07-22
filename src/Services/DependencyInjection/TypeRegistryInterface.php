<?php

namespace Graphael\Services\DependencyInjection;

interface TypeRegistryInterface
{
    public function get($className);
}
