<?php declare(strict_types=1);

namespace LinkORB\Bundle\GraphaelBundle\DependencyInjection;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\PhpFileCache;
use LinkORB\Bundle\GraphaelBundle\Services\FieldResolver;
use LinkORB\Bundle\GraphaelBundle\Services\Server;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
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

        if ($options['pdo_url'] ?? null) {
            $container->setParameter('graphael.pdo_url', $options['pdo_url']);
        }

        if (!isset($options['type_namespace'])) {
            throw new RuntimeException("type_namespace not configured");
        }
        if (!isset($options['type_path'])) {
            throw new RuntimeException("type_path not configured");
        }

        $this->processTypes($options, $container);

        $this->initializeServer($options['type_namespace'], $options['type_postfix'], $container);
    }

    private function processCacheDriver(array $options, ContainerBuilder $container): void
    {
        $cacheDriver = $options['cache_driver'] ?? null;
        try {
            $cacheDriver = $container->getParameterBag()->resolveValue($cacheDriver);
        } catch (ParameterNotFoundException) {
            // Value is set not as parameter, so let's use it as it is
        }

        switch ($cacheDriver) {
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

    private function processTypes(array $options, ContainerBuilder $container): void
    {
        // Auto register QueryTypes
        foreach (glob($options['type_path'] . '/*Type.php') as $filename) {
            $className = $options['type_namespace'] . '\\' . basename($filename, '.php');
            if (!is_array(class_implements($className))) {
                throw new RuntimeException("Can't register class (failed to load, or does not implement anything): " . $className);
            }
            if (is_subclass_of($className, 'GraphQL\\Type\\Definition\\Type')) {
                $container->autowire($className, $className)->setPublic(true);
            }
        }
    }

    private function initializeServer(string $typeNamespace, string $typePostfix, ContainerBuilder $container): void
    {
        $container->getDefinition(Server::class)
            ->addArgument(new Reference($typeNamespace . '\QueryType'))
            ->addArgument(new Reference($typeNamespace . '\MutationType'))
            ->addArgument([])
            ->addArgument(new Reference(AuthorizationCheckerInterface::class))
            ->addArgument('%'.Server::CONTEXT_ADMIN_ROLE_KEY.'%')
            ->addArgument(new Reference('request_stack'))
            ->addArgument(new Reference(FieldResolver::class))
            ->addArgument(new Reference('service_container'))
            ->addArgument($typeNamespace)
            ->addArgument($typePostfix)
        ;
    }
}
