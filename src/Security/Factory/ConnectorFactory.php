<?php declare(strict_types=1);

namespace LinkORB\Bundle\GraphaelBundle\Security\Factory;

use Connector\Connector;
use PDO;

class ConnectorFactory
{
    public static function createConnector(string $pdoUrl): PDO
    {
        $connector = new Connector();

        $pdoConfig = $connector->getConfig($pdoUrl);
        $mode = 'db';
        $pdoDsn = $connector->getPdoDsn($pdoConfig, $mode);

        return new PDO(
            $pdoDsn,
            $pdoConfig->getUsername(),
            $pdoConfig->getPassword(),
            [PDO::MYSQL_ATTR_FOUND_ROWS => true]
        );
    }
}
