<?php declare(strict_types=1);

namespace Graphael\Security;

interface JwtManagerInterface
{
    public function getPublicCertificate(string $username): string;
}
