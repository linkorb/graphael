<?php declare(strict_types=1);

namespace LinkORB\Bundle\GraphaelBundle\Security\UserProvider;

use Symfony\Component\Security\Core\User\UserInterface;

interface JwtDataMapperInterface
{
    public function getUsernameProperty(): string;

    public function getUserTable(): string;

    public function map(array $object): UserInterface;
}
