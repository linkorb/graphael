<?php declare(strict_types=1);

namespace Graphael\Security;

use Graphael\Entity\Security\UsernameAuthorization;
use Graphael\Security\Authorization\UsernameVoter;
use Graphael\Server;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

trait AuthorizationContextAwareTrait
{
    private function assertSameUsername(array $context, ?string $username): void
    {
        $this->assertGranted($context, [UsernameVoter::USER_ROLE], new UsernameAuthorization($username));
    }

    private function assertGranted(array $context, array $attributes, $subject = null): void
    {
        $checker = $context[Server::CONTEXT_AUTHORIZATION_KEY];

        assert($checker instanceof AuthorizationCheckerInterface);

        if (!$checker->isGranted($attributes, $subject)) {
            throw new AccessDeniedException();
        }
    }
}
