<?php declare(strict_types=1);

namespace LinkORB\Bundle\GraphaelBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('graphael');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('jwt_algo')->defaultValue('%graphael.jwt_algo%')->end()
                ->scalarNode('jwt_key')->defaultValue('%graphael.jwt_key%')->end()
                ->scalarNode('jwt_username_claim')->defaultValue('%graphael.jwt_username_claim%')->end()
                ->scalarNode('jwt_roles_claim')->defaultValue('%graphael.jwt_roles_claim%')->end()
                ->scalarNode('admin_role')->defaultValue('%graphael.admin_role%')->end()
                ->scalarNode('jwt_default_role')->defaultValue('%graphael.jwt_default_role%')->end()
                ->scalarNode('cache_driver')->defaultValue('%graphael.cache_driver%')->end()
                ->scalarNode('cache_driver_file_path')->defaultValue('%graphael.cache_driver_file_path%')->end()
                ->scalarNode('pdo_url')->end()
                ->scalarNode('type_namespace')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('type_path')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('type_postfix')->isRequired()->cannotBeEmpty()->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
