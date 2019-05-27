<?php declare(strict_types=1);

namespace Graphael\Security;

use Graphael\Server;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

trait AuthorizationContextAwareTrait
{
    private function isGranted(array $context, array $attributes, $subject = null): bool
    {
        $checker = $context[Server::CONTEXT_AUTHORIZATION_KEY];

        assert($checker instanceof AuthorizationCheckerInterface);

        return $checker->isGranted($attributes, $subject);
    }
}
