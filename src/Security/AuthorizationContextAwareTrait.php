<?php

declare(strict_types=1);

namespace Graphael\Security;

use Graphael\Entity\Security\UsernameAuthorization;
use Graphael\Security\Authorization\UsernameVoter;
use Graphael\Server;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

trait AuthorizationContextAwareTrait
{
    protected function assertSameUsername(array $context, ?string $username): void
    {
        $this->assertGranted(
            $context,
            [UsernameVoter::USER_ROLE, $context[Server::CONTEXT_ADMIN_ROLE_KEY]],
            new UsernameAuthorization($username),
            'Access to another user\'s data denied'
        );
    }

    protected function assertGranted(
        array $context,
        array $attributes,
        $subject = null,
        string $deniedMessage = 'Access Denied.'
    ): void {
        $checker = $context[Server::CONTEXT_AUTHORIZATION_KEY];

        assert($checker instanceof AuthorizationCheckerInterface);

        if (!$checker->isGranted($attributes, $subject)) {
            throw new AccessDeniedException($deniedMessage);
        }
    }
}
