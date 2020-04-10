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

    public function __construct(
        ObjectType $queryType,
        ObjectType $mutationType,
        callable $typeLoader,
        array $rootValue,
        AuthorizationCheckerInterface $authorizationChecker,
        string $adminRole,
        int $debugFlag = 0
    ) {
        $schema = new Schema(
            [
                'query' => $queryType,
                'mutation' => $mutationType,
                'typeLoader' => $typeLoader,
            ]
        );

        $debug = ($debugFlag) ? Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE : $debugFlag;

        $config = [
            'schema' => $schema,
            'debug' => $debug,
            'rootValue' => $rootValue,
            'fieldResolver' => [new FieldResolver(), 'resolve'],
            'context' => [
                static::CONTEXT_AUTHORIZATION_KEY => $authorizationChecker,
                static::CONTEXT_ADMIN_ROLE_KEY => $adminRole,
            ],
        ];

        parent::__construct($config);
    }
}
