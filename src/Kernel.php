<?php declare(strict_types=1);

namespace Graphael;

use Graphael\Security\JwtManagerInterface;
use Graphael\Security\SecurityChecker;
use Graphael\Services\DependencyInjection\ContainerFactory;
use Graphael\Services\Error\ErrorHandler;
use Graphael\Services\Error\ErrorHandlerInterface;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Responsible for services instantiation & configuration
 * Class Kernel
 * @package Graphael
 */
class Kernel
{
    /** @var array */
    private $serverConfig;

    /** @var StandardServer */
    private $server;

    /** @var ErrorHandlerInterface */
    private $errorHandler;

    /** @var UserProviderInterface */
    private $userProvider;

    /** @var JwtManagerInterface */
    private $jwtManager;

    public function __construct(array $serverConfig)
    {
        $this->serverConfig = $serverConfig;
    }

    public function run(): void
    {
        $this->boot($this->serverConfig);

        $this->errorHandler->initialize();

        $this->server->handleRequest();
    }

    public function setUserProvider(UserProviderInterface $userProvider): self
    {
        $this->userProvider = $userProvider;

        return $this;
    }

    public function setJwtManager(JwtManagerInterface $jwtManager): self
    {
        $this->jwtManager = $jwtManager;

        return $this;
    }

    private function boot(array $config): void
    {
        // Create container
        $container = ContainerFactory::create($config);

        $container->set(ContainerFactory::JWT_USER_PROVIDER, $this->userProvider);
        $container->set(ContainerFactory::JWT_CERT_MANAGER, $this->jwtManager);

        $this->errorHandler = $container->get(ErrorHandler::class);

        $rootValue = [];

        $request = Request::createFromGlobals();

        if ($container->getParameter('jwt_enabled', true)) {
            /** @var SecurityChecker $securityChecker */
            $securityChecker = $container->get(SecurityChecker::class);
            $jwtAuthenticated = $securityChecker->check($request);

            $rootValue['token'] = $jwtAuthenticated->getCredentials();
            $rootValue['username'] = $jwtAuthenticated->getUsername();
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
}
