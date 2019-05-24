<?php declare(strict_types=1);

namespace Graphael\Security\Provider;

use Exception;
use Firebase\JWT\JWT;
use Graphael\Security\JWTManagerInterface;
use Graphael\Security\Token\JsonWebToken;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class JwtAuthProvider implements AuthenticationProviderInterface
{
    /**  @var UserProviderInterface */
    private $userProvider;

    /** @var JWTManagerInterface */
    private $jwtManager;

    /** @var string */
    private $jwtAlg;

    public function __construct(UserProviderInterface $userProvider, JWTManagerInterface $jwtManager, string $jwtAlg)
    {
        $this->userProvider = $userProvider;
        $this->jwtManager = $jwtManager;
        $this->jwtAlg = $jwtAlg;
    }

    public function authenticate(TokenInterface $token): TokenInterface
    {
        try {
            $payload = JWT::decode(
                $token->getCredentials(),
                $this->jwtManager->getPublicCertificate($token->getUsername()),
                [$this->jwtAlg]
            );

            if (!$payload) {
                throw new AuthenticationException();
            }
        } catch (Exception $e) {
            throw new AuthenticationException('Token invalid');
        }

        $user = $this->userProvider->loadUserByUsername($token->getUsername());

        $authToken = new JsonWebToken($user->getRoles());
        $authToken->setUser($user);
        $authToken->setAuthenticated(true);

        return $authToken;
    }

    public function supports(TokenInterface $token): bool
    {
        return $token instanceof JsonWebToken;
    }
}
