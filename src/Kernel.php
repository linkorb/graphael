<?php declare(strict_types=1);

namespace Graphael;

use Graphael\Security\SecurityFacade;
use Graphael\Services\DependencyInjection\ContainerFactory;
use Graphael\Services\Error\ErrorHandlerInterface;
use GraphQL\Server\StandardServer;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;
use GraphQL\Type\Definition\ObjectType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Graphael\Services\FieldResolver;
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


    /**
     * @param ExecutionResult|mixed[] $result
     *
     * @return int
     */
    private function resolveHttpStatus($result)
    {
        if (is_array($result) && isset($result[0])) {
            Utils::each(
                $result,
                static function ($executionResult, $index) : void {
                    if (! $executionResult instanceof ExecutionResult) {
                        throw new InvariantViolation(sprintf(
                            'Expecting every entry of batched query result to be instance of %s but entry at position %d is %s',
                            ExecutionResult::class,
                            $index,
                            Utils::printSafe($executionResult)
                        ));
                    }
                }
            );
            $httpStatus = 200;
        } else {
            if (! $result instanceof ExecutionResult) {
                throw new InvariantViolation(sprintf(
                    'Expecting query result to be instance of %s but got %s',
                    ExecutionResult::class,
                    Utils::printSafe($result)
                ));
            }
            if ($result->data === null && count($result->errors) > 0) {
                $httpStatus = 400;
            } else {
                $httpStatus = 200;
            }
        }

        return $httpStatus;
    }


    public function run(Request $request): Response
    {
        $container = $this->boot($this->serverConfig);
        $this->initialize($container, $request);

        $logger = null;
        if (isset($this->serverConfig[ContainerFactory::LOGGER])) {
            $logger = $container->get(
                $this->serverConfig[ContainerFactory::LOGGER]
            );
        }

        $result = $this->server->executeRequest(); // ExecutionResult
        $httpStatus = $this->resolveHttpStatus($result);

        if (count($logger->getHandlers())>0) {
            $result->setErrorsHandler(function($errors) use ($logger) {
                foreach ($errors as $error) {
                    $json = json_encode($error, JSON_UNESCAPED_SLASHES);
                    $data = [
                        'event' => [
                            'action' => 'graphael:error',
                        ],
                        'log' => [
                            'level' => 'error',
                            'original' => json_encode(['error' => $json], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                        ],
                    ];
                    $logger->error($error->getMessage() ?? 'Execution Error', $data);
                }
                return array_map('GraphQL\Error\FormattedError::createFromException', $errors);
            });
        }
        $data = $result->toArray();
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $response = new Response($json, $httpStatus);

        if ($httpStatus!=200) {
            $data = [
                'event' => [
                    'action' => 'graphael:error',
                ],
                'log' => [
                    'level' => 'error',
                    'original' => 'HTTP' . $httpStatus . ': ' . json_encode(['error' => $json], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                ],
            ];
            $logger->error('HTTP Error', $data);
        }

        return $response;
    }

    private function boot(array $config): ContainerInterface
    {
        // Create container
        $container = ContainerFactory::create($config);
        $container->compile();

        return $container;
    }

    private function initialize(ContainerInterface $container, Request $request): void
    {
        /** @var ErrorHandlerInterface $errorHandler */
        $errorHandler = $container->get(ErrorHandlerInterface::class);
        $errorHandler->initialize();

        $rootValue = [];

        $container->set(Request::class, $request);

        $logger = null;
        if (isset($this->serverConfig[ContainerFactory::LOGGER])) {
            $logger = $container->get(
                $this->serverConfig[ContainerFactory::LOGGER]
            );
            $errorHandler->setLogger($logger);
        }

        /** @var SecurityFacade $securityFacade */
        $securityFacade = $container->get(SecurityFacade::class);
        $securityFacade->initialize(
            $request,
            (bool) $container->getParameter('jwt_key'),
            $container->getParameter('jwt_username_claim'),
            $container->getParameter('jwt_roles_claim'),
            $container->getParameter('jwt_default_role'),
            $container->getParameter(Server::CONTEXT_ADMIN_ROLE_KEY)
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
            $container->getParameter(Server::CONTEXT_ADMIN_ROLE_KEY),
            $request,
            new FieldResolver($logger)
        );
    }
}
