<?php

namespace App\Helpers;

class Logger
{
    private string $logDir;

    public function __construct(string $logDir = __DIR__ . '/../../logs')
    {
        $this->logDir = $logDir;

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }

    public function info(string $message): void
    {
        $this->write('app.log', "[INFO] " . $message);
    }

    public function error(string $message): void
    {
        $this->write('error.log', "[ERROR] " . $message);
    }

    private function write(string $file, string $message): void
    {
        $path = "{$this->logDir}/{$file}";
        $timestamp = date('[Y-m-d H:i:s]');
        file_put_contents($path, "{$timestamp} {$message}" . PHP_EOL, FILE_APPEND);
    }
}