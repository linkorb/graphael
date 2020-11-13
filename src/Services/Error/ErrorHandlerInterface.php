<?php

namespace Graphael\Services\Error;

use Monolog\Logger;

interface ErrorHandlerInterface
{
    public function initialize(): void;

    public function onShutdown(): void;

    public function setLogger(Logger $logger): void;
}
