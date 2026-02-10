<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\JsonFormatter as MonologJsonFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

class JsonFormatter
{
    public function __invoke($logger): void
    {
        if ($logger instanceof IlluminateLogger) {
            $logger = $logger->getLogger();
        }

        if (!$logger instanceof Logger) {
            return;
        }

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
