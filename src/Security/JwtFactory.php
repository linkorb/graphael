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

    private $usernameClaim = self::USERNAME_CLAIM_ID;

    public function createFromRequest(Request $request): TokenInterface
    {
        $rawJwtString = $this->extractRawJwt($request);

        $jwtSegments = explode('.', $rawJwtString);

        if (count($jwtSegments) != 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }

        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($jwtSegments[1]));

        $token = new JsonWebToken([], $rawJwtString);

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

    private function extractRawJwt(Request $request): string
    {
        // try to extract JWT from HTTP headers
        if ($request->headers->has('HTTP_X_AUTHORIZATION')) {
            $auth = $request->headers->get('HTTP_X_AUTHORIZATION', '');

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
