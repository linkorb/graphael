<?php declare(strict_types=1);

namespace LinkORB\GraphaelBundle\DependencyInjection;

use Connector\Connector;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\PhpFileCache;
use LinkORB\GraphaelBundle\Controller\GraphController;
use LinkORB\GraphaelBundle\Services\DependencyInjection\TypeRegistryInterface;
use LinkORB\GraphaelBundle\Services\FieldResolver;
use LinkORB\GraphaelBundle\Services\Server;
use PDO;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class GraphaelExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configDir = new FileLocator(__DIR__ . '/../../config');
        // Load the bundle's service declarations
        $loader = new YamlFileLoader($container, $configDir);
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $options = $this->processConfiguration($configuration, $configs);

        $this->processCacheDriver($options, $container);

        if (file_exists($options['jwt_key'])) {
            $jwtKey = file_get_contents($options['jwt_key']);
            $options['jwt_key'] = $jwtKey;
        }

        $this->processConnector($options, $container);

        if (!isset($options['type_namespace'])) {
            throw new RuntimeException("type_namespace not configured");
        }
        if (!isset($options['type_path'])) {
            throw new RuntimeException("type_path not configured");
        }

        $container->getDefinition(TypeRegistryInterface::class)->addArgument($container);

        $this->processTypes($options, $container);

        $container->register($options['logger']);

        $container->getDefinition(GraphController::class)->setArgument('$logger', new Reference($options['logger']));
        $container->getDefinition(FieldResolver::class)->addArgument(new Reference($options['logger']));

        $this->initializeServer($options['type_namespace'], $options['type_postfix'], $container);
    }

    private function processCacheDriver(array $options, ContainerBuilder $container): void
    {
        switch ($options['cache_driver']) {
            case 'file':
                $container
                    ->register(Cache::class, PhpFileCache::class)
                    ->addArgument($options['cache_driver_file_path'])
                ;
                break;
            case ''; // default unconfigured to array
            case 'array':
                $container
                    ->register(Cache::class, ArrayCache::class)
                ;
                break;
            default:
                throw new RuntimeException("Unsupported or unconfigured cache driver: " . $options['cache_driver']);
        }
    }

    private function processConnector(array $options, ContainerBuilder $container): void
    {
        $connector = $container->get(Connector::class);

        $pdoConfig = $connector->getConfig($options['pdo_url']);
        $mode = 'db';
        $pdoDsn = $connector->getPdoDsn($pdoConfig, $mode);

        $container->getDefinition(PDO::class)
            ->addArgument($pdoDsn)
            ->addArgument($pdoConfig->getUsername())
            ->addArgument($pdoConfig->getPassword())
            ->addArgument([
                PDO::MYSQL_ATTR_FOUND_ROWS => true
            ])
        ;
    }

    private function processTypes(array $options, ContainerBuilder $container): void
    {
        // Auto register QueryTypes
        foreach (glob($options['type_path'] . '/*Type.php') as $filename) {
            $className = $options['type_path'] . '\\' . basename($filename, '.php');
            if (!is_array(class_implements($className))) {
                throw new RuntimeException("Can't register class (failed to load, or does not implement anything): " . $className);
            }
            if (is_subclass_of($className, 'GraphQL\\Type\\Definition\\Type')) {
                $container->getDefinition($className)->setPublic(true);
            }
        }
    }

    private function initializeServer(string $typeNamespace, string $typePostfix, ContainerBuilder $container): void
    {
        $container->getDefinition(Server::class)
            ->addArgument(new Reference($typeNamespace . '\QueryType'))
            ->addArgument(new Reference($typeNamespace . '\MutationType'))
            ->addArgument(function ($name) use ($container, $typeNamespace, $typePostfix) {
                $className = $typeNamespace . '\\' . $name . $typePostfix;
                return $container->get($className);
            })
            ->addArgument([])
            ->addArgument(new Reference(AuthorizationCheckerInterface::class))
            ->addArgument('%'.Server::CONTEXT_ADMIN_ROLE_KEY.'%')
            ->addArgument(new Reference('request_stack'))
            ->addArgument(new Reference(FieldResolver::class))
        ;
    }
}
