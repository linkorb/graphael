<?php declare(strict_types=1);

namespace Graphael\Entity\Security;

class UsernameAuthorization implements AuthorizationEntityInterface
{
    private $accessedUsername;

    public function __construct($accessedUsername)
    {
        $this->accessedUsername = $accessedUsername;
    }

    public function getAccessedUsername(): string
    {
        return $this->accessedUsername;
    }
}
