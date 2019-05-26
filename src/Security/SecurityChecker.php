<?php declare(strict_types=1);

namespace Graphael\Security;

use Exception;
use Graphael\Security\Provider\JwtAuthProvider;
use Graphael\Security\Token\JsonWebToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SecurityChecker
{
    public const ANONYMOUS_USER = 'anonymous';

    /** @var JwtFactory */
    private $jwtFactory;

    /** @var JwtAuthProvider */
    private $jwtAuthProvider;

    /** @var string|null */
    private $usernameClaim;

    public function __construct(JwtFactory $jwtFactory, JwtAuthProvider $jwtAuthProvider, string $usernameClaim = null)
    {
        $this->jwtFactory = $jwtFactory;
        $this->jwtAuthProvider = $jwtAuthProvider;
        $this->usernameClaim = $usernameClaim;
    }

    public function check(Request $request, bool $jwtEnabled): TokenInterface
    {
        if (!$jwtEnabled) {
            $token = new JsonWebToken([]);
            $token->setUser(static::ANONYMOUS_USER);
            $token->setAuthenticated(true);
        }

        try {
            if ($this->usernameClaim) {
                $this->jwtFactory->setUsernameClaim($this->usernameClaim);
            }

            return $this->jwtAuthProvider->authenticate($this->jwtFactory->createFromRequest($request));
        } catch (Exception $e) {
            throw new AuthenticationException('Token invalid', 0, $e);
        }
    }
}
