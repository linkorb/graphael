<?php declare(strict_types=1);

namespace LinkORB\GraphaelBundle\Security\JwtCertManager;

class JwtCertManager implements JwtCertManagerInterface
{
    public function __construct(
        private string $publicCert,
    ) {}

    public function getPublicCertificate(string $username): string
    {
        return $this->publicCert;
    }
}
