<?php declare(strict_types=1);

namespace LinkORB\GraphaelBundle\Security;

use Firebase\JWT\JWT;
use LinkORB\GraphaelBundle\Exception\OmittedJwtTokenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use UnexpectedValueException;

class JwtFactory
{
    public const ANONYMOUS_USER = 'anonymous';
    public const USERNAME_CLAIM_ID = 'username';
    public const ROLES_CLAIM_ID = 'roles';
    public const DEFAULT_ROLE = 'ROLE_AUTHENTICATED';

    public function __construct(
        private bool $jwtEnabled,
        private string $adminRole,
        private string $usernameClaim = self::USERNAME_CLAIM_ID,
        private string $rolesClaim = self::ROLES_CLAIM_ID,
        private ?string $defaultRole = self::DEFAULT_ROLE,
    ) {}

    public function createFromRequest(Request $request): InMemoryUser
    {
        // Disabled jwt auth means admin role for every request
        if (!$this->jwtEnabled) {
            return new InMemoryUser(static::ANONYMOUS_USER, null, [$this->adminRole]);
        }

        $rawJwtString = $this->extractRawJwt($request);

        $jwtSegments = explode('.', $rawJwtString);

        if (count($jwtSegments) != 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }

        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($jwtSegments[1]));

        if (!$payload->{$this->usernameClaim}) {
            throw new AuthenticationException('Username claim should exists in JWT');
        }

        $roles = $payload->{$this->rolesClaim} ?? [$this->defaultRole];
        if (is_string($roles)) {
            $roles = explode(",", $roles);
        }

        if (empty($payload->{$this->usernameClaim})) {
            throw new AuthenticationException('No username claim passed in JWT');
        }

        return new InMemoryUser($payload->{$this->usernameClaim}, $rawJwtString, $roles);
    }

    private function extractRawJwt(Request $request): string
    {
        // try to extract JWT from HTTP headers
        // Using `X-Authorization`, as `Authorization` gets lost in an apache2 proxypass
        if ($request->headers->has('X-Authorization')) {
            $auth = $request->headers->get('X-Authorization', '');

            $authPart = explode(' ', $auth);

            if (count($authPart) !== 2) {
                throw new OmittedJwtTokenException('Invalid authorization header');
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
        throw new OmittedJwtTokenException('Token required');
    }
}
