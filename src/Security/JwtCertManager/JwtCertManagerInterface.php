<?php declare(strict_types=1);

namespace LinkORB\GraphaelBundle\Security\JwtCertManager;

interface JwtCertManagerInterface
{
    public function getPublicCertificate(string $username): string;
}
