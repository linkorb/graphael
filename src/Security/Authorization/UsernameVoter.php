<?php declare(strict_types=1);

namespace LinkORB\GraphaelBundle\Security\Authorization;

use LinkORB\GraphaelBundle\Entity\Security\UsernameAuthorization;
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

        return $token->getUser()->getUserIdentifier() === $subject->getAccessedUsername();
    }
}
