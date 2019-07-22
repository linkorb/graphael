<?php

namespace Graphael\Security\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class JsonWebToken extends AbstractToken
{
    /** @var string */
    private $rawToken;

    public function __construct(array $roles = [], string $rawToken = null)
    {
        parent::__construct($roles);

        $this->rawToken = $rawToken;
    }

    public function getCredentials(): ?string
    {
        return $this->rawToken;
    }
}
