<?php declare(strict_types=1);

namespace LinkORB\Bundle\GraphaelBundle\Entity\Security;

class UsernameAuthorization implements AuthorizationEntityInterface
{
    /** @var string|null */
    private $accessedUsername;

    public function __construct(?string $accessedUsername)
    {
        $this->accessedUsername = $accessedUsername;
    }

    public function getAccessedUsername(): ?string
    {
        return $this->accessedUsername;
    }
}
