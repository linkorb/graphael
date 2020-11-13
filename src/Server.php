<?php

namespace Graphael;

use Graphael\Services\FieldResolver;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Error\Debug;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class Server extends StandardServer
{
    public const CONTEXT_AUTHORIZATION_KEY = 'authorization';
    public const CONTEXT_ADMIN_ROLE_KEY = 'admin_role';
    public const CONTEXT_IP_KEY = 'ip';

    public function __construct(
        ObjectType $queryType,
        ObjectType $mutationType,
        callable $typeLoader,
        array $rootValue,
        AuthorizationCheckerInterface $authorizationChecker,
        string $adminRole,
        $request
    )
    {
        $schema = new Schema(
            [
                'query' => $queryType,
                'mutation' => $mutationType,
                'typeLoader' => $typeLoader,
            ]
        );

        $config = [
            'schema' => $schema,
            'debug' => Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE,
            'rootValue' => $rootValue,
            'fieldResolver' => [new FieldResolver(), 'resolve'],
            'context' => [
                static::CONTEXT_AUTHORIZATION_KEY => $authorizationChecker,
                static::CONTEXT_ADMIN_ROLE_KEY => $adminRole,
                static::CONTEXT_IP_KEY => $request->getClientIp(),
            ],
        ];


        parent::__construct($config);
    }
}
