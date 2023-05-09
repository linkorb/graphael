<?php

namespace LinkORB\GraphaelBundle\Services\DependencyInjection;

interface TypeRegistryInterface
{
    public function get($className);
}
