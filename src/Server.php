<?php

namespace Graphael;

use Graphael\Services\FieldResolver;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Error\Debug;

class Server extends StandardServer
{
    public function __construct(
        ObjectType $queryType,
        ObjectType $mutationType,
        callable $typeLoader,
        array $rootValue
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
        ];

        parent::__construct($config);
    }
}
