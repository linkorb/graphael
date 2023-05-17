<?php

declare(strict_types=1);

namespace LinkORB\Bundle\GraphaelBundle\Security;

use LinkORB\Bundle\GraphaelBundle\Entity\Security\UsernameAuthorization;
use LinkORB\Bundle\GraphaelBundle\Security\Authorization\UsernameVoter;
use LinkORB\Bundle\GraphaelBundle\Services\Server;
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
