<?php

namespace Graphael\Services\DependencyInjection;

use Graphael\Security\Authorization\UsernameVoter;
use Graphael\Security\JwtFactory;
use Graphael\Security\Provider\JwtAuthProvider;
use Graphael\Security\SecurityFacade;
use Graphael\Services\Error\ErrorHandler;
use Graphael\Services\Error\ErrorHandlerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Connector\Connector;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ContainerFactory
{
    public const JWT_USER_PROVIDER = 'graphael.jwt.uers_provider';
    public const JWT_CERT_MANAGER = 'graphael.jwt.certificate_manager';

    public static function create($config)
    {
        $environmentPrefix = $config['environment_prefix'];
        $parameters = self::getParameters($environmentPrefix);

        // Build the DI container
        $container = new ContainerBuilder();
        foreach ($config as $key=>$value) {
            $container->setParameter($key, $value);
        }
        foreach ($parameters as $key=>$value) {
            $container->setParameter($key, $value);
        }

        // === setup database connection ===s
        $connector = new Connector();
        $container
            ->register('connector', 'Connector\Connector')
        ;

        $pdoConfig = $connector->getConfig($parameters['pdo_url']);
        $mode = 'db';
        $pdoDsn = $connector->getPdoDsn($pdoConfig, $mode);

        //throw new \Exception($config->getPassword());
        $container
            ->register('PDO', 'PDO')
            ->addArgument($pdoDsn)
            ->addArgument($pdoConfig->getUsername())
            ->addArgument($pdoConfig->getPassword())
        ;

        // == register all GraphQL Types ===
        if (!isset($config['type_namespace'])) {
            throw new RuntimeException("type_namespace not configured");
        }
        if (!isset($config['type_path'])) {
            throw new RuntimeException("type_path not configured");
        }
        $ns = $config['type_namespace'];
        $path = $config['type_path'];
        if (!file_exists($path)) {
            throw new RuntimeException("Invalid type_path (not found)");
        }

        // Register TypeRegister
        $definition = $container->register(TypeRegistryInterface::class, ContainerTypeRegistry::class);
        $definition->addArgument($container);

        static::registerSecurityServices($container);

        // Auto register QueryTypes
        foreach (glob($path.'/*Type.php') as $filename) {
            $className = $ns . '\\' . basename($filename, '.php');
            if (!is_array(class_implements($className))) {
                throw new RuntimeException("Can't register class (failed to load, or does not implement anything): " . $className);
            }
            if (in_array('GraphQL\\Type\\Definition\\OutputType', class_implements($className))) {
                self::autoRegisterClass($container, $className)
                    ->setPublic(true);
            }
        }

        return $container;

    }

    public static function normalizedVoters(array $voters): array
    {
        $normalizedVoters = [];

        foreach ($voters as $voter) {
            if (is_string($voter)) {
                $normalizedVoters[] = new Reference($voter);
            } elseif ($voter instanceof VoterInterface) {
                $normalizedVoters[] = $voter;
            }
        }

        return $normalizedVoters;
    }

    private static function registerSecurityServices(ContainerBuilder $container): void
    {
        $container->register(ErrorHandlerInterface::class, ErrorHandler::class)->setPublic(true);
        $container->register(JwtFactory::class, JwtFactory::class);

        $authProviderDefinition = $container->register(JwtAuthProvider::class, JwtAuthProvider::class);
        $authProviderDefinition->addArgument(new Reference(static::JWT_USER_PROVIDER));
        $authProviderDefinition->addArgument(new Reference(static::JWT_CERT_MANAGER));
        $authProviderDefinition->addArgument($container->getParameter('jwt_algo'));

        $container->setAlias(AuthenticationProviderInterface::class, JwtAuthProvider::class);

        $container->register(TokenStorageInterface::class, TokenStorage::class);

        $authenticationManager = $container->register(
            AuthenticationManagerInterface::class,
            AuthenticationProviderManager::class
        );
        $authenticationManager->addArgument([new Reference(JwtAuthProvider::class)]);

        $accessDecisionManager = $container->register(
            AccessDecisionManagerInterface::class,
            AccessDecisionManager::class
        );
        // Will be defined in Kernel
        $accessDecisionManager->addArgument(null);
        $accessDecisionManager->addArgument(AccessDecisionManager::STRATEGY_UNANIMOUS);
        $accessDecisionManager->addArgument(false);
        $accessDecisionManager->addArgument(false);

        $checkerDefinition = static::autoRegisterClass($container, SecurityFacade::class)
            ->setPublic(true);
        if ($container->hasParameter('jwt_username_claim')) {
            $checkerDefinition->addArgument($container->getParameter('jwt_username_claim'));
        }

        static::autoRegisterClass($container, AuthorizationChecker::class);
        $container->setAlias(AuthorizationCheckerInterface::class, AuthorizationChecker::class)
            ->setPublic(true);

        $container->register(RoleVoter::class, RoleVoter::class);
        $container->register(UsernameVoter::class, UsernameVoter::class);
    }

    private static function getParameters($prefix)
    {
        // Load parameters from environment
        $parameters = [];
        foreach ($_ENV as $key=>$value) {
            if (substr($key, 0, strlen($prefix))==$prefix) {
                if (is_numeric($value)) {
                    $value = (int)$value;
                }
                $parameters[strtolower(substr($key, strlen($prefix)))] = $value;
            }
        }

        // Validate parameters
        $resolver = new OptionsResolver();

        $resolver->setDefaults(array(
            'debug' => false,
            'jwt_algo' => 'RS256',
            'jwt_enabled' => 1,
        ));
        $resolver->setAllowedTypes('jwt_enabled', ['int']);
        $resolver->setRequired('pdo_url');
        return $resolver->resolve($parameters);
    }

    private static function autoRegisterClass(ContainerBuilder $container, $className): Definition
    {
        $reflectionClass = new ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();
        $definition = $container->register($className, $className);
        foreach ($constructor->getParameters() as $p) {
            $reflectionClass = $p->getClass();
            if ($reflectionClass) {
                $definition->addArgument(new Reference($reflectionClass->getName()));
            }
        }

        return $definition;
    }
}
