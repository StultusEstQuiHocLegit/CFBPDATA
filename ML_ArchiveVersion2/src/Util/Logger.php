<?php
// logger.php
// Provides a lightweight logger that collects timestamped entries for streaming.

declare(strict_types=1);

namespace App\Util;

// Logger accumulates log messages with timestamps,
// it does not output directly but instead stores messages for later retrieval,
// using the flush() method to retrieve and clear messages at the end of a request if needed
class Logger
{
    // @var array<int,string>
    private array $entries = [];

    private function add(string $level, string $message): void
    {
        $ts = date('[H:i:s]');
        $this->entries[] = "$ts [$level] $message";
    }

    public function info(string $msg): void
    {
        $this->add('INFO', $msg);
    }

    public function warn(string $msg): void
    {
        $this->add('WARN', $msg);
    }

    public function error(string $msg): void
    {
        $this->add('ERROR', $msg);
    }

    // Return all accumulated entries and clear the buffer
    // @return string[]
    public function flush(): array
    {
        $entries = $this->entries;
        $this->entries = [];
        return $entries;
    }
}