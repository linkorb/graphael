<?php declare(strict_types=1);

namespace Graphael\Security;

use Firebase\JWT\JWT;
use Graphael\Security\Token\JsonWebToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use UnexpectedValueException;

class JwtFactory
{
    public const USERNAME_CLAIM_ID = 'username';
    public const ROLES_CLAIM_ID = 'roles';
    public const DEFAULT_ROLE = 'ROLE_AUTHENTICATED';

    private $usernameClaim = self::USERNAME_CLAIM_ID;

    private $rolesClaim = self::ROLES_CLAIM_ID;

    private $defaultRole = self::DEFAULT_ROLE;

    public function createFromRequest(Request $request): TokenInterface
    {
        $rawJwtString = $this->extractRawJwt($request);

        $jwtSegments = explode('.', $rawJwtString);

        if (count($jwtSegments) != 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }

        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($jwtSegments[1]));

        if (!$payload->{$this->usernameClaim}) {
            throw new AuthenticationException('Username claim should exists in JWT');
        }

        $token = new JsonWebToken($payload->{$this->rolesClaim} ?? [$this->defaultRole], $rawJwtString);

        if (empty($payload->{$this->usernameClaim})) {
            throw new AuthenticationException('No username claim passed in JWT');
        }

        $token->setUser($payload->{$this->usernameClaim});

        return $token;
    }

    public function setUsernameClaim(string $usernameClaim): self
    {
        $this->usernameClaim = $usernameClaim;

        return $this;
    }

    public function setRolesClaim(string $rolesClaim): self
    {
        $this->rolesClaim = $rolesClaim;

        return $this;
    }

    public function setDefaultRole(string $defaultRole): self
    {
        $this->defaultRole = $defaultRole;

        return $this;
    }

    private function extractRawJwt(Request $request): string
    {
        // try to extract JWT from HTTP headers
        if ($request->headers->has('Authorization')) {
            $auth = $request->headers->get('Authorization', '');

            $authPart = explode(' ', $auth);

            if (count($authPart) !== 2) {
                throw new AuthenticationException('Invalid authorization header');
            }

            if ($authPart[0] !== 'Bearer') {
                throw new AuthenticationException('Invalid authorization type');
            }

            return end($authPart);
        }

        // try to extract JWT from GET parameters
        if (!empty($request->get('jwt'))) {
            return $request->get('jwt');
        }

        // jwt_key configured, but no jwt provided in request
        throw new AuthenticationException('Token required');
    }
}
