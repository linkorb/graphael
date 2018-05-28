<?php

namespace Graphael;

use Graphael\ContainerFactory;
use GraphQL\Server\StandardServer;
use GraphQL\Server\ServerConfig;
use GraphQL\Type\Schema;
use GraphQL\Error\Debug;
use RuntimeException;
use Firebase\JWT\JWT;

class Server extends StandardServer
{
    public function __construct(array $config)
    {
        // Setup custom shutdownHandler for improved debugging
        error_reporting(E_ALL);
        //ini_set('display_errors', 1); // enable during container building

        header('Access-Control-Allow-Origin: *');
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method=='OPTIONS') {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            echo '{"status": "ok"}';
            exit();;
        }

        ob_start();
        register_shutdown_function([$this, "shutdownHandler"]);
        ini_set('display_errors', 0); // disable - let server + shutdown handler output errors

        // Create container
        $container = ContainerFactory::create($config);

        $rootValue = [];

        // JWT?
        $jwtKey = $container->getParameter('jwt_key');

        if ($jwtKey) {
            if ($jwtKey[0]=='/') { // absolute path
                if (!file_exists($jwtKey)) {
                    throw new RuntimeException("File not found: $jwtKey");
                }
                $jwtKey = file_get_contents($jwtKey);
                $container->setParameter('jwt_key', $jwtKey);
            } else {
                // inline jwt
                $container->setParameter('jwt_key', $jwtKey);
            }

            $jwt = null;
            // try to extract JWT from HTTP headers
            if (isset($_SERVER['HTTP_X_AUTHORIZATION'])) {
                $auth = $_SERVER['HTTP_X_AUTHORIZATION'];
                $authPart = explode(' ', $auth);
                if (count($authPart)!=2) {
                    throw new RuntimeException("Invalid authorization header");
                }
                if ($authPart[0]!='Bearer') {
                    throw new RuntimeException("Invalid authorization type");
                }
                $jwt = $authPart[1];
            }
            // try to extract JWT from GET parameters
            if (isset($_GET['jwt'])) {
                $jwt = $_GET['jwt'];
            }

            if (!$jwt) {
                // jwt_key configured, but no jwt provided in request
                throw new RuntimeException("Token required");
            }
            $token = null;
            try {
                $token = (array)JWT::decode($jwt, $jwtKey, array('RS256'));
            } catch (\Exception $e) {
                throw new RuntimeException("Token invalid");
            }
            if (!$token) {
                throw new RuntimeException("Invalid JWT");
            }
            $rootValue['token'] = $token;
            if (isset($token['username'])) {
                $rootValue['username'] = $token['username'];
            }
            $rootValue['someRootValue'] = 'test';
        }

        $typeNamespace = $container->getParameter('type_namespace');
        $typePostfix = $container->getParameter('type_postfix');
        $schema = new Schema(
            [
                'query' => $container->get($typeNamespace . '\QueryType'),
                'mutation' => $container->get($typeNamespace . '\MutationType'),
                'typeLoader' => function ($name) use ($container, $typeNamespace, $typePostfix) {
                    $className = $typeNamespace . '\\' . $name . $typePostfix;
                    return $container->get($className);
                }
            ]
        );

        $debug = Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE;

        $fieldResolver = new FieldResolver();

        $config = [
            'schema' => $schema,
            'debug' => $debug,
            'rootValue' => $rootValue,
            'fieldResolver' => [$fieldResolver, 'resolve']
        ];

        parent::__construct($config);
    }

    public function shutdownHandler()
    {
        $output = ob_get_contents();
        $error = error_get_last();
        if ($error) {
            ob_end_clean(); // ignore the buffer
            echo json_encode(['error' => $error], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
            return;
        }
    }
}
