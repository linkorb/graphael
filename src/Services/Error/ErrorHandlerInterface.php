<?php

namespace Graphael\Services\Error;

interface ErrorHandlerInterface
{
    public function initialize(): void;

    public function onShutdown(): void;
}
