<?php
// config.php
// Loads configuration from JSON files into a strongly typed object.

declare(strict_types=1);

namespace App\Config;

// Config is a simple wrapper over associative arrays loaded from JSON,
// it exposes nested structures via readonly properties,
// missing keys throw exceptions to catch configuration errors early
class Config implements \JsonSerializable
{
    // @var array<string,mixed>
    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    // Load a configuration file from a JSON path
    // @param string $path
    public static function fromFile(string $path): self
    {
        if (!is_readable($path)) {
            throw new \RuntimeException("Cannot read config file: $path");
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to load config: $path");
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON config: $path");
        }
        return new self($data);
    }

    // Provide read-only property access through the magic getter,
    // nested arrays are recursively wrapped into config objects
    // @param string $name
    // @return mixed
    public function __get(string $name)
    {
        if (!array_key_exists($name, $this->data)) {
            throw new \OutOfBoundsException("Unknown config key: $name");
        }
        $value = $this->data[$name];
        if (is_array($value) && self::isAssoc($value)) {
            return new self($value);
        }
        return $value;
    }

    // Determine if an array is associative
    // @param array<mixed> $arr
    private static function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    // Implement JsonSerializable so the config can be encoded to JSON easily
    public function jsonSerialize(): mixed
    {
        return $this->data;
    }
}