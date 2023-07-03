<?php

namespace LinkORB\Bundle\GraphaelBundle\Services;

use GraphQL\Error\DebugFlag;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class Server extends StandardServer
{
    public const CONTEXT_AUTHORIZATION_KEY = 'authorization';
    public const CONTEXT_ADMIN_ROLE_KEY = 'graphael.admin_role';
    public const CONTEXT_IP_KEY = 'ip';

    public function __construct(
        ObjectType $queryType,
        ObjectType $mutationType,
        array $rootValue,
        AuthorizationCheckerInterface $authorizationChecker,
        string $adminRole,
        RequestStack $requestStack,
        $resolver,
        ContainerInterface $container,
        string $typeNamespace,
        string $typePostfix
    ) {
        $schema = new Schema(
            [
                'query' => $queryType,
                'mutation' => $mutationType,
                'typeLoader' => function ($name) use ($container, $typeNamespace, $typePostfix) {
                    $className = $typeNamespace . '\\' . $name . $typePostfix;
                    return $container->get($className);
                },
            ]
        );

        $config = [
            'schema' => $schema,
            'debugFlag' => DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE,
            'rootValue' => $rootValue,
            'fieldResolver' => [$resolver, 'resolve'],
            'context' => [
                static::CONTEXT_AUTHORIZATION_KEY => $authorizationChecker,
                static::CONTEXT_ADMIN_ROLE_KEY => $adminRole,
                static::CONTEXT_IP_KEY => $requestStack->getCurrentRequest()->getClientIp(),
            ],
        ];


        parent::__construct($config);
    }
}
