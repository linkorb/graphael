<?php declare(strict_types=1);

namespace LinkORB\GraphaelBundle\Security\UserProvider;

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

    public function getInsertSql(): string
    {
        $sql = sprintf(
            'INSERT INTO
                `%s`
                (%s, display_name, first_stamp, created_at)
                VALUES (:username, :displayName, :now, :now)',
            $this->getUserTable(),
            $this->getUsernameProperty()
        );
        return $sql;
    }

    public function getInsertKeys(): array
    {
        return [
            ':username',
            ':now',
            ':displayName',
        ];
    }

    public function map(array $object): UserInterface
    {
        return new User($object[$this->getUsernameProperty()], null, []);
    }
}
