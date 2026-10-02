<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Support;

use Bedriox\Api\Plugin\PluginLogger;

final readonly class AuditLogger
{
    public function __construct(
        private PluginLogger $logger,
        private string $dataPath,
        private bool $enabled,
    ) {}

    public function record(string $message): void
    {
        $this->logger->info($message);
        if (!$this->enabled) {
            return;
        }
        $line = '[' . date(DATE_ATOM) . '] ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL;
        @file_put_contents($this->dataPath . DIRECTORY_SEPARATOR . 'audit.log', $line, FILE_APPEND | LOCK_EX);
    }
}
