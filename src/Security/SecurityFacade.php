<?php declare(strict_types=1);

namespace Graphael\Security;

use Exception;
use Graphael\Security\Token\JsonWebToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SecurityFacade
{
    public const ANONYMOUS_USER = 'anonymous';

    /** @var JwtFactory */
    private $jwtFactory;

    /** @var string|null */
    private $usernameClaim;

    /** @var TokenStorageInterface */
    private $tokenStorage;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        JwtFactory $jwtFactory,
        string $usernameClaim = null
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->jwtFactory = $jwtFactory;
        $this->usernameClaim = $usernameClaim;
    }

    public function initialize(Request $request, bool $jwtEnabled): void
    {
        // Anonymous user means absence of jwt auth
        if (!$jwtEnabled) {
            $token = new JsonWebToken([]);
            $token->setUser(static::ANONYMOUS_USER);
            $token->setAuthenticated(true);

            $this->tokenStorage->setToken($token);

            return;
        }

        try {
            if ($this->usernameClaim) {
                $this->jwtFactory->setUsernameClaim($this->usernameClaim);
            }

            $this->tokenStorage->setToken($this->jwtFactory->createFromRequest($request));
        } catch (Exception $e) {
            throw new AuthenticationException('Token invalid', 0, $e);
        }
    }
}
