<?php declare(strict_types=1);

namespace Graphael\Security\UserProvider;

use PDO;
use RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class JwtUserProvider implements UserProviderInterface
{
    /** @var PDO */
    private $pdo;

    /** @var JwtDataMapperInterface */
    private $dataMapper;

    public function __construct(PDO $pdo, JwtDataMapperInterface $dataMapper)
    {
        $this->pdo = $pdo;
        $this->dataMapper = $dataMapper;
    }

    public function loadUserByUsername($username): UserInterface
    {
        return $this->getUser($username);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->getUser($user->getUsername());
    }

    public function supportsClass($class): bool
    {
        return $class === UserInterface::class;
    }

    private function getUser(string $username): UserInterface
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE %s = :username',
            $this->dataMapper->getUserTable(),
            $this->dataMapper->getUsernameProperty()
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':username' => $username]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $userData = reset($results);

        if (!$userData) {
            $sql = $this->dataMapper->getInsertSql();

            $stmt = $this->pdo->prepare($sql);

            // Add any data we can obtain from the JWT or request
            $data = [
                ':username' => $username,
                ':displayName' => $username,
                ':now' => time()
            ];

            // Filter the data to only required keys for insert
            $keys = $this->dataMapper->getInsertKeys();
            foreach ($data as $k=>$v) {
                if (!in_array($k, $keys)) {
                   unset($data[$k]);
                }
            }

            // Perform insert query
            $stmt->execute($data);

            // Re-run SELECT
            $sql = sprintf(
                'SELECT * FROM `%s` WHERE %s = :username',
                $this->dataMapper->getUserTable(),
                $this->dataMapper->getUsernameProperty()
            );
    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':username' => $username]);
    
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $userData = reset($results);

            if (!$userData) {
                throw new RuntimeException("Failed to create user from JWT");
            }
        }

        return $this->dataMapper->map($userData);
    }
}
