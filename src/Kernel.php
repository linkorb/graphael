<?php declare(strict_types=1);

namespace Graphael;

use Firebase\JWT\JWT;
use Graphael\Services\DependencyInjection\ContainerFactory;
use Graphael\Services\Error\ErrorHandler;
use Graphael\Services\Error\ErrorHandlerInterface;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Responsible for services instantiation & configuration
 * Class Kernel
 * @package Graphael
 */
class Kernel
{
    /** @var StandardServer */
    private $server;

    /** @var ErrorHandlerInterface */
    private $errorHandler;

    public function __construct(array $serverConfig)
    {
        $this->boot($serverConfig);
    }

    public function run(): void
    {
        $this->errorHandler->initialize();

        $this->server->handleRequest();
    }

    private function boot(array $config): void
    {
        $this->errorHandler = new ErrorHandler();

        // Create container
        $container = ContainerFactory::create($config);

        $rootValue = [];

        // JWT?
        $jwtKey = $container->getParameter('jwt_key');

        if ($jwtKey) {
            $this->checkAuth($jwtKey, $container, $rootValue);
        }

        $typeNamespace = $container->getParameter('type_namespace');
        $typePostfix = $container->getParameter('type_postfix');

        /** @var ObjectType $queryType */
        $queryType = $container->get($typeNamespace . '\QueryType');
        /** @var ObjectType $mutationType */
        $mutationType = $container->get($typeNamespace . '\MutationType');

        $this->server = new Server(
            $queryType,
            $mutationType,
            function ($name) use ($container, $typeNamespace, $typePostfix) {
                $className = $typeNamespace . '\\' . $name . $typePostfix;
                return $container->get($className);
            },
            $rootValue
        );
    }

    /** TODO: Replace with Symfony Security */
    private function checkAuth(string $jwtKey, ContainerBuilder $container, array &$rootValue)
    {
        if ($jwtKey[0] == '/') { // absolute path
            if (!file_exists($jwtKey)) {
                throw new RuntimeException("File not found: $jwtKey");
            }
            $jwtKey = file_get_contents($jwtKey);
            $container->setParameter('jwt_key', $jwtKey);
        }

        $jwt = null;
        // try to extract JWT from HTTP headers
        if (isset($_SERVER['HTTP_X_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_X_AUTHORIZATION'];
            $authPart = explode(' ', $auth);
            if (count($authPart) != 2) {
                throw new RuntimeException("Invalid authorization header");
            }
            if ($authPart[0] != 'Bearer') {
                throw new RuntimeException("Invalid authorization type");
            }
            $jwt = $authPart[1];
        }
        // try to extract JWT from GET parameters
        if (isset($_GET['jwt'])) {
            $jwt = $_GET['jwt'];
        }

        if (!$jwt) {
            // jwt_key configured, but no jwt provided in request
            throw new RuntimeException("Token required");
        }
        $token = null;
        try {
            $token = (array)JWT::decode($jwt, $jwtKey, array('RS256'));
        } catch (\Exception $e) {
            throw new RuntimeException("Token invalid");
        }
        if (!$token) {
            throw new RuntimeException("Invalid JWT");
        }
        $rootValue['token'] = $token;
        if (isset($token['username'])) {
            $rootValue['username'] = $token['username'];
        }
        $rootValue['someRootValue'] = 'test';
    }
}
