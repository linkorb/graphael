<?php declare(strict_types=1);

namespace Graphael\Security\Authorization;

use Graphael\Entity\Security\UsernameAuthorization;
use Graphael\Security\SecurityFacade;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UsernameVoter extends Voter
{
    public const USER_ROLE = 'USERNAME_ACCESS_ROLE';

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof UsernameAuthorization && $attribute === static::USER_ROLE;
    }

    public function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        assert($subject instanceof UsernameAuthorization);
        assert($attribute === static::USER_ROLE);

        if ($token->getUsername() === SecurityFacade::ANONYMOUS_USER && empty($token->getCredentials())) {
            return true;
        }

        return $token->getUsername() === $subject->getAccessedUsername();
    }
}
