<?php

namespace Graphael;

use Graphael\ContainerFactory;
use GraphQL\Server\StandardServer;
use GraphQL\Server\ServerConfig;
use GraphQL\Type\Schema;
use GraphQL\Error\Debug;

class Server extends StandardServer
{
    public function __construct($config)
    {
        // Setup custom shutdownHandler for improved debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 1); // enable during container building

        // Create container
        $container = ContainerFactory::create($config);

        ob_start();
        register_shutdown_function([$this, "shutdownHandler"]);
        ini_set('display_errors', 0); // disable - let server + shutdown handler output errors

        $schema = new Schema([
            'query' => $container->get($container->getParameter('type_namespace') . '\QueryType\RootQueryType'),
            'typeLoader' => function($name) use ($container) {
                $className = $container->getParameter('type_namespace') . '\\QueryType\\' . $name . 'QueryType';
                return $container->get($className);
            }
        ]);

        $debug = Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE;
        $config = [
            'schema' => $schema,
            'debug' => $debug
        ];

        parent::__construct($config);
    }

    public function shutdownHandler() {
        $output = ob_get_contents();
        $error = error_get_last();
        if ($error) {
            ob_end_clean(); // ignore the buffer
            echo json_encode(['error' => $error], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
            return;
        }
    }
}
