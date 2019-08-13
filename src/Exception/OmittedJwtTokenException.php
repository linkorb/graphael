<?php declare(strict_types=1);

namespace Graphael\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class OmittedJwtTokenException extends AuthenticationException
{
}
