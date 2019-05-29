<?php declare(strict_types=1);

namespace Graphael\Security\Provider;

use Firebase\JWT\JWT;
use Graphael\Security\JwtCertManager\JwtCertManagerInterface;
use Graphael\Security\Token\JsonWebToken;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class JwtAuthProvider implements AuthenticationProviderInterface
{
    /**  @var UserProviderInterface */
    private $userProvider;

    /** @var JwtCertManagerInterface */
    private $jwtManager;

    /** @var string */
    private $jwtAlg;

    public function __construct(UserProviderInterface $userProvider, JwtCertManagerInterface $jwtManager, string $jwtAlg)
    {
        $this->userProvider = $userProvider;
        $this->jwtManager = $jwtManager;
        $this->jwtAlg = $jwtAlg;
    }

    public function authenticate(TokenInterface $token): TokenInterface
    {
        $payload = JWT::decode(
            $token->getCredentials(),
            $this->jwtManager->getPublicCertificate($token->getUsername()),
            [$this->jwtAlg]
        );

        if (!$payload) {
            throw new AuthenticationException();
        }

        $user = $this->userProvider->loadUserByUsername($token->getUsername());

        $authToken = new JsonWebToken($token->getRoles(), $token->getCredentials());
        $authToken->setUser($user);
        $authToken->setAuthenticated(true);

        return $authToken;
    }

    public function supports(TokenInterface $token): bool
    {
        return $token instanceof JsonWebToken;
    }
}
