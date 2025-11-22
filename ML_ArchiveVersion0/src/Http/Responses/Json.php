<?php
// Json.php
// Provides a simple JSON HTTP response wrapper.

declare(strict_types=1);

namespace App\Http\Responses;

// Json encapsulates a HTTP response that outputs a JSON payload, it sets appropriate headers when sending, instances are immutable
class Json
{
    // @var array<string,mixed>
    private array $payload;
    // @var int
    private int $status;

    private function __construct(array $payload, int $status = 200)
    {
        $this->payload = $payload;
        $this->status = $status;
    }

    // Create a successful response
    // @param array<string,mixed> $data
    public static function success(array $data): self
    {
        return new self(['status' => 'ok', 'data' => $data], 200);
    }

    // Create an error response
    // @param string $message
    public static function error(string $message, int $status = 400): self
    {
        return new self(['status' => 'error', 'message' => $message], $status);
    }

    // Send the JSON response to the client
    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json');
        echo json_encode($this->payload);
    }
}