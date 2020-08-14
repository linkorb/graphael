<?php

namespace Graphael\Services\DependencyInjection;

use Graphael\Security\Authorization\UsernameVoter;
use Graphael\Security\JwtCertManager\JwtCertManager;
use Graphael\Security\JwtCertManager\JwtCertManagerInterface;
use Graphael\Security\JwtFactory;
use Graphael\Security\Provider\JwtAuthProvider;
use Graphael\Security\SecurityFacade;
use Graphael\Security\UserProvider\DefaultJwtDataMapper;
use Graphael\Security\UserProvider\JwtDataMapperInterface;
use Graphael\Security\UserProvider\JwtUserProvider;
use Graphael\Server;
use Graphael\Services\Error\ErrorHandler;
use Graphael\Services\Error\ErrorHandlerInterface;
use PDO;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Connector\Connector;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Spark\Spark;
use Spark\EventDispatcher\SparkEventDispatcherV4;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ContainerFactory
{
    public const AUTH_VOTERS = 'auth_voters';
    public const ROLE_HIERARCHY = 'role_hierarchy';

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

        if (file_exists($container->getParameter('jwt_key'))) {
            $jwtKey = file_get_contents($container->getParameter('jwt_key'));
            $container->setParameter('jwt_key', $jwtKey);
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
            ->addArgument([
                PDO::MYSQL_ATTR_FOUND_ROWS => true
            ])
        ;


        $spark = Spark::getInstance();
        $container->set(Spark::class, $spark);

        $dispatcher = new EventDispatcher();
        $container->register(
                EventDispatcherInterface::class,
                SparkEventDispatcherV4::class
            )
            ->addArgument($dispatcher)
            ->addArgument($spark)
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

        static::registerSecurityServices($container, $config);

        // Auto register QueryTypes
        foreach (glob($path.'/*Type.php') as $filename) {
            $className = $ns . '\\' . basename($filename, '.php');
            if (!is_array(class_implements($className))) {
                throw new RuntimeException("Can't register class (failed to load, or does not implement anything): " . $className);
            }
            if (is_subclass_of($className, 'GraphQL\\Type\\Definition\\Type')) {
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

    private static function registerSecurityServices(ContainerBuilder $container, array $config): void
    {
        $container->register(ErrorHandlerInterface::class, ErrorHandler::class)->setPublic(true);
        $container->register(JwtFactory::class, JwtFactory::class);

        $container->setAlias(AuthenticationProviderInterface::class, JwtAuthProvider::class);

        $container->register(TokenStorageInterface::class, TokenStorage::class);

        $authenticationManager = $container->register(
            AuthenticationManagerInterface::class,
            AuthenticationProviderManager::class
        );
        $authenticationManager->addArgument([new Reference(JwtAuthProvider::class)]);

        $container->register(AccessDecisionManagerInterface::class, AccessDecisionManager::class)
            ->addArgument(static::normalizedVoters($config[static::AUTH_VOTERS]))
            ->addArgument(AccessDecisionManager::STRATEGY_AFFIRMATIVE)
            ->addArgument(false);

        static::autoRegisterClass($container, SecurityFacade::class)
            ->setPublic(true);

        static::autoRegisterClass($container, AuthorizationChecker::class);
        $container->setAlias(AuthorizationCheckerInterface::class, AuthorizationChecker::class)
            ->setPublic(true);

        $container->register(RoleVoter::class, RoleVoter::class);
        if ($config[static::ROLE_HIERARCHY]) {
            $container->register(RoleHierarchyInterface::class, RoleHierarchy::class)
                ->addArgument($config[static::ROLE_HIERARCHY]);
            $container->register(RoleHierarchyVoter::class, RoleHierarchyVoter::class)
                ->addArgument(new Reference(RoleHierarchyInterface::class));
        }
        $container->register(UsernameVoter::class, UsernameVoter::class);

        static::autoRegisterClass($container, AuthenticationTrustResolver::class);
        $container->setAlias(AuthenticationTrustResolverInterface::class, AuthenticationTrustResolver::class);
        static::autoRegisterClass($container, AuthenticatedVoter::class);

        $container->register(JwtCertManager::class, JwtCertManager::class)
            ->addArgument($container->getParameter('jwt_key'));

        static::registerOrAlias($container, JwtCertManagerInterface::class, $config[JwtCertManagerInterface::class]);

        $container->register(DefaultJwtDataMapper::class, DefaultJwtDataMapper::class);

        static::registerOrAlias($container, JwtDataMapperInterface::class, $config[JwtDataMapperInterface::class]);

        if ($container->has(JwtDataMapperInterface::class)) {
            $container->register(JwtUserProvider::class, JwtUserProvider::class)
                ->addArgument(new Reference(PDO::class))
                ->addArgument(new Reference(JwtDataMapperInterface::class));
        }

        static::registerOrAlias($container, UserProviderInterface::class, $config[UserProviderInterface::class]);

        $authProviderDefinition = $container->register(JwtAuthProvider::class, JwtAuthProvider::class);
        $authProviderDefinition->addArgument(new Reference(UserProviderInterface::class));
        $authProviderDefinition->addArgument(new Reference(JwtCertManagerInterface::class));
        $authProviderDefinition->addArgument($container->getParameter('jwt_algo'));
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
            'jwt_key' => null,
            'jwt_username_claim' => null,
            'jwt_roles_claim' => null,
            Server::CONTEXT_ADMIN_ROLE_KEY => 'ROLE_ADMIN',
            'jwt_default_role' => null,
        ));
        $resolver->setAllowedTypes('jwt_key', ['string', 'null']);
        $resolver->setAllowedTypes('jwt_username_claim', ['string', 'null']);
        $resolver->setAllowedTypes('jwt_roles_claim', ['string', 'null']);
        $resolver->setAllowedTypes(Server::CONTEXT_ADMIN_ROLE_KEY, ['string']);
        $resolver->setAllowedTypes('jwt_default_role', ['string', 'null']);
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

    private static function registerOrAlias(ContainerBuilder $container, string $id, $value): void
    {
        if (is_object($value)) {
            $container->set($id, $value);
        } elseif (is_string($value)) {
            $container->setAlias($id, $value);
        }
    }
}
