<?php declare(strict_types=1);

namespace LinkORB\Bundle\GraphaelBundle\Controller;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Utils\Utils;
use LinkORB\Bundle\GraphaelBundle\Services\Server;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class GraphController
{
    public function __construct(
        private Server $server,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request)
    {
        $response = new JsonResponse();
        $response->headers->set('Access-Control-Allow-Origin', '*');

        if ($request->isMethod('OPTIONS')) {
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Authorization');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
            $response->setContent('{"status": "ok"}');

            return $response;
        }

        $result = $this->server->executeRequest();
        $httpStatus = $this->resolveHttpStatus($result);

        if (count($this->logger->getHandlers())>0) {
            $result->setErrorsHandler(function($errors) {
                foreach ($errors as $error) {
                    $json = json_encode($error, JSON_UNESCAPED_SLASHES);
                    $data = [
                        'event' => [
                            'action' => 'graphael:error',
                        ],
                        'log' => [
                            'level' => 'error',
                            'original' => json_encode(['error' => $json], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                        ],
                    ];
                    $this->logger->error($error->getMessage() ?? 'Execution Error', $data);
                }
                return array_map('GraphQL\Error\FormattedError::createFromException', $errors);
            });
        }

        $data = $result->toArray();
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $response->setContent($json);
        $response->setStatusCode($httpStatus);

        if ($httpStatus!=200) {
            $data = [
                'event' => [
                    'action' => 'graphael:error',
                ],
                'log' => [
                    'level' => 'error',
                    'original' => 'HTTP' . $httpStatus . ': ' . json_encode(['error' => $json], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                ],
            ];
            $this->logger->error('HTTP Error', $data);
        }

        return $response;
    }

    /**
     * @param ExecutionResult|mixed[] $result
     */
    private function resolveHttpStatus($result): int
    {
        if (is_array($result) && isset($result[0])) {
            foreach ($result as $index => $executionResultItem) {
                if (!$executionResultItem instanceof ExecutionResult) {
                    throw new InvariantViolation(sprintf(
                        'Expecting every entry of batched query result to be instance of %s but entry at position %d is %s',
                        ExecutionResult::class,
                        $index,
                        Utils::printSafe($executionResultItem)
                    ));
                }
            }
            $httpStatus = 200;
        } else {
            if (!$result instanceof ExecutionResult) {
                throw new InvariantViolation(sprintf(
                    'Expecting query result to be instance of %s but got %s',
                    ExecutionResult::class,
                    Utils::printSafe($result)
                ));
            }
            if ($result->data === null && count($result->errors) > 0) {
                $httpStatus = 400;
            } else {
                $httpStatus = 200;
            }
        }

        return $httpStatus;
    }
}
