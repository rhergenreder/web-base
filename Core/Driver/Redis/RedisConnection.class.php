<?php

namespace Core\Driver\Redis;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\SQL;
use Core\Objects\ConnectionData;

class RedisConnection {

  private \Redis $link;

  private Logger $logger;

  public function __construct(?SQL $sql) {
    $this->logger = new Logger("Redis", $sql);
    $this->link = new \Redis();
  }

  public function connect(ConnectionData $connectionData): bool {
    try {
      $this->link->connect($connectionData->getHost(), $connectionData->getPort());
      $this->link->auth($connectionData->getPassword());
      return true;
    } catch (\RedisException $e) {
      $this->logger->error("Error connecting to redis: " . $e->getMessage());
      return false;
    }
  }

  public function isConnected(): bool {
    try {
      return $this->link->isConnected();
    } catch (\RedisException $e) {
      $this->logger->error("Error checking redis connection: " . $e->getMessage());
      return false;
    }
  }

  public function hGet(string $hashKey, string $key): ?string {
    try {
      return $this->link->hGet($hashKey, $key);
    } catch (\RedisException $e) {
      $this->logger->error("Error fetching value from redis: " . $e->getMessage());
      return null;
    }
  }

  public function hSet(string $hashKey, mixed $key, string $value): bool {
    try {
      return $this->link->hSet($hashKey, $key, $value);
    } catch (\RedisException $e) {
      $this->logger->error("Error setting value: " . $e->getMessage());
      return false;
    }
  }

  public function close(): bool {
    try {
      return $this->link->close();
    } catch (\RedisException) {
      return false;
    }
  }

}