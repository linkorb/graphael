<?php

namespace Graphael;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Psr\Container\ContainerInterface;
use Connector\Connector;
use ReflectionClass;
use PDO;

class ContainerFactory
{
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
        $definition = $container->register(TypeRegistry::class, TypeRegistry::class);
        $definition->addArgument($container);

        // Auto register QueryTypes
        foreach (glob($path.'/QueryType/*QueryType.php') as $filename) {
            $className = $ns . '\\QueryType\\' . basename($filename, '.php');
            if (in_array('GraphQL\\Type\\Definition\\OutputType', class_implements($className))) {
                self::autoRegisterClass($container, $className);
            }
        }
        // Auto register MutationTypes
        foreach (glob($path.'/MutationType/*MutationType.php') as $filename) {
            $className = $ns . '\\MutationType\\' . basename($filename, '.php');
            if (in_array('GraphQL\\Type\\Definition\\OutputType', class_implements($className))) {
                self::autoRegisterClass($container, $className);
            }
        }

        return $container;

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
        ));
        $resolver->setRequired('jwt_key');
        $resolver->setRequired('pdo_url');
        return $resolver->resolve($parameters);
    }

    private static function autoRegisterClass(ContainerInterface $container, $className)
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
    }
}
