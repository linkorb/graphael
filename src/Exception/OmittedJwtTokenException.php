<?php declare(strict_types=1);

namespace LinkORB\Bundle\GraphaelBundle\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class OmittedJwtTokenException extends AuthenticationException
{
}
