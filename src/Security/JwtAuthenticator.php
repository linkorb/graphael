<?php declare(strict_types=1);

namespace LinkORB\GraphaelBundle\Security;

use Firebase\JWT\JWT;
use LinkORB\GraphaelBundle\Exception\OmittedJwtTokenException;
use LinkORB\GraphaelBundle\Security\JwtCertManager\JwtCertManagerInterface;
use LinkORB\GraphaelBundle\Security\UserProvider\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use UnexpectedValueException;

class JwtAuthenticator extends AbstractAuthenticator
{
    private ?InMemoryUser $user;

    public function __construct(
        private JwtFactory              $factory,
        private JwtCertManagerInterface $jwtManager,
        private UserProviderInterface   $userProvider,
        private string                  $jwtAlg
    ) {}

    /**
     * @inheritDoc
     */
    public function supports(Request $request): ?bool
    {
        try {
            $this->user = $this->factory->createFromRequest($request);
        } catch (OmittedJwtTokenException) {
            return true;
        } catch (AuthenticationException|UnexpectedValueException) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function authenticate(Request $request): Passport
    {
        try {
            $this->user = $this->user ?? $this->factory->createFromRequest($request);
        } catch (OmittedJwtTokenException) {
            return new SelfValidatingPassport(
                new UserBadge(
                    uniqid(),
                    function(): UserInterface {
                        return new InMemoryUser(JwtFactory::ANONYMOUS_USER, null, []);
                    }
                )
            );
        }

        $payload = JWT::decode(
            $this->user->getPassword(),
            $this->jwtManager->getPublicCertificate($this->user->getUserIdentifier()),
            [$this->jwtAlg]
        );

        if (!$payload) {
            throw new AuthenticationException();
        }

        $authenticatedUsername = $this->userProvider
            ->loadUserByIdentifier($this->user->getUserIdentifier())
            ->getUserIdentifier();

        $authenticatedUser = new InMemoryUser($authenticatedUsername, $this->user->getPassword(),
            $this->user->getRoles());

        return new SelfValidatingPassport(
            new UserBadge(
                $authenticatedUser->getUserIdentifier(),
                function(string $username): UserInterface {
                    $user = $this->userProvider->loadUserByIdentifier($username);
                    if (!$user instanceof User) {
                        throw new AuthenticationException(sprintf('%s user object expected', User::class));
                    }

                    $user->setRoles($this->user->getRoles());

                    return $user;
                }
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response($exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }
}
