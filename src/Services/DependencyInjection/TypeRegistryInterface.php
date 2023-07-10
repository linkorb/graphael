<?php

namespace LinkORB\Bundle\GraphaelBundle\Services\DependencyInjection;

interface TypeRegistryInterface
{
    public function get($className);
}
