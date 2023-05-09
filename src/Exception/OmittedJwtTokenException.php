<?php declare(strict_types=1);

namespace LinkORB\GraphaelBundle\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class OmittedJwtTokenException extends AuthenticationException
{
}
