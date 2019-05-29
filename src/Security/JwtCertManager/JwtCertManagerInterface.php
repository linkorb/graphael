<?php declare(strict_types=1);

namespace Graphael\Security\JwtCertManager;

interface JwtCertManagerInterface
{
    public function getPublicCertificate(string $username): string;
}
