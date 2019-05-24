<?php declare(strict_types=1);

namespace Graphael\Security;

use Exception;
use Graphael\Security\Provider\JwtAuthProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class SecurityChecker
{
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

    public function check(Request $request): TokenInterface
    {
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
