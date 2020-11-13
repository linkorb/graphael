<?php declare(strict_types=1);

namespace Graphael\Services\Error;

use Monolog\Logger;

final class ErrorHandler implements ErrorHandlerInterface
{
    public function initialize(): void
    {
        // Setup custom shutdownHandler for improved debugging
        error_reporting(E_ALL);

        header('Access-Control-Allow-Origin: *');
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == 'OPTIONS') {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization');
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            echo '{"status": "ok"}';
            exit();
        }
        ob_start();
        register_shutdown_function([$this, 'onShutdown']);
        ini_set('display_errors', '0'); // disable - let server + shutdown handler output errors
    }

    protected $logger;

    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    public function onShutdown(): void
    {
        $error = error_get_last();

        if ($error) {
            ob_end_clean(); // ignore the buffer
            echo json_encode(['error' => $error], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($this->logger) {
                $data = [
                    'event' => [
                        'action' => 'graphael:error',
                    ],
                    'log' => [
                        'level' => 'error',
                        'original' => json_encode(['error' => $error], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    ],
                ];
                $this->logger->info('Error Handler', $data);
            }

            return;
        }
    }
}
