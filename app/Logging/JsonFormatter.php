<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter as MonologJsonFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

class JsonFormatter
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $this->applyFormatter($handler);
        }
    }

    private function applyFormatter(HandlerInterface $handler): void
    {
        $formatter = new MonologJsonFormatter(
            MonologJsonFormatter::BATCH_MODE_JSON,
            true,
            true,
            true
        );

        $handler->setFormatter($formatter);
    }
}
