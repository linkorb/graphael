<?php declare(strict_types=1);

namespace Graphael\Security\JwtCertManager;

class JwtCertManager implements JwtCertManagerInterface
{
    /** @var string */
    private $publicCert;

    public function __construct(string $publicCert)
    {
        $this->publicCert = $publicCert;
    }

    public function getPublicCertificate(string $username): string
    {
        return $this->publicCert;
    }
}
