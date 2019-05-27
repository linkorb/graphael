<?php

namespace Graphael;

use Graphael\Services\FieldResolver;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Error\Debug;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class Server extends StandardServer
{
    public const CONTEXT_AUTHENTICATION_KEY = 'authentication';

    public function __construct(
        ObjectType $queryType,
        ObjectType $mutationType,
        callable $typeLoader,
        array $rootValue,
        TokenInterface $token
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
                static::CONTEXT_AUTHENTICATION_KEY => $token,
            ],
        ];

        parent::__construct($config);
    }
}
