<?php declare(strict_types=1);

namespace LinkORB\Bundle\GraphaelBundle\Security\JwtCertManager;

interface JwtCertManagerInterface
{
    public function getPublicCertificate(string $username): string;
}
