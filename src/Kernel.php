<?php declare(strict_types=1);

namespace Graphael;

use Graphael\Security\SecurityFacade;
use Graphael\Services\DependencyInjection\ContainerFactory;
use Graphael\Services\Error\ErrorHandlerInterface;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

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

    public function __construct(array $serverConfig)
    {
        $this->serverConfig = $serverConfig;
    }

    public function run(): void
    {
        $container = $this->boot($this->serverConfig);
        $this->initialize($container);

        $this->server->handleRequest();
    }

    private function boot(array $config): ContainerInterface
    {
        // Create container
        $container = ContainerFactory::create($config);

        $container->compile();

        return $container;
    }

    private function initialize(ContainerInterface $container): void
    {
        /** @var ErrorHandlerInterface $errorHandler */
        $errorHandler = $container->get(ErrorHandlerInterface::class);
        $errorHandler->initialize();

        $rootValue = [];

        $request = Request::createFromGlobals();

        /** @var SecurityFacade $securityFacade */
        $securityFacade = $container->get(SecurityFacade::class);
        $securityFacade->initialize(
            $request,
            (bool) $container->getParameter('jwt_key'),
            $container->getParameter('jwt_username_claim'),
            $container->getParameter('jwt_roles_claim'),
            $container->getParameter('jwt_default_role')
        );

        $typeNamespace = $container->getParameter('type_namespace');
        $typePostfix = $container->getParameter('type_postfix');

        /** @var ObjectType $queryType */
        $queryType = $container->get($typeNamespace . '\QueryType');
        /** @var ObjectType $mutationType */
        $mutationType = $container->get($typeNamespace . '\MutationType');

        /** @var AuthorizationCheckerInterface $checker */
        $checker = $container->get(AuthorizationCheckerInterface::class);

        $this->server = new Server(
            $queryType,
            $mutationType,
            function ($name) use ($container, $typeNamespace, $typePostfix) {
                $className = $typeNamespace . '\\' . $name . $typePostfix;
                return $container->get($className);
            },
            $rootValue,
            $checker,
            $container->getParameter(Server::CONTEXT_ADMIN_ROLE_KEY)
        );
    }
}
