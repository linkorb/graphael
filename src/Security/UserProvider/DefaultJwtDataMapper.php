<?php declare(strict_types=1);

namespace Graphael\Security\UserProvider;

use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class DefaultJwtDataMapper implements JwtDataMapperInterface
{
    public function getUsernameProperty(): string
    {
        return 'username';
    }

    public function getUserTable(): string
    {
        return 'user_data';
    }

    public function map(array $object): UserInterface
    {
        return new User($object[$this->getUsernameProperty()], null);
    }
}
