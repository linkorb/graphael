<?php declare(strict_types=1);

namespace LinkORB\Bundle\GraphaelBundle\Security\Authorization;

use LinkORB\Bundle\GraphaelBundle\Entity\Security\UsernameAuthorization;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UsernameVoter extends Voter
{
    public const USER_ROLE = 'USERNAME_ACCESS_ROLE';

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof UsernameAuthorization &&
            is_array($attribute) &&
            in_array(static::USER_ROLE, $attribute, true)
        ;
    }

    public function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        assert($subject instanceof UsernameAuthorization);
        assert(in_array(static::USER_ROLE, $attribute, true));

        return $token->getUser()->getUserIdentifier() === $subject->getAccessedUsername();
    }
}
