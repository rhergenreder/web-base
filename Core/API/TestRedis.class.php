<?php
      
namespace Core\API;

use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Group;

class TestRedis extends Request {

  public function __construct(Context $context, bool $externalCall = false) {
    parent::__construct($context, $externalCall, []);
  }
   
  protected function _execute(): bool {

    $settings = $this->context->getSettings();
    if (!$settings->isRateLimitingEnabled()) {
      return $this->createError("Rate Limiting is currently disabled");
    }

    $connection = $this->context->getRedis();
    if ($connection === null || !$connection->isConnected()) {
      return $this->createError("Redis connection failed");
    }

    return $this->success;
  }

  public static function getDescription(): string {
    return "Allows users to test the redis connection with the configured credentials.";
  }

  public static function getDefaultPermittedGroups(): array {
    return [Group::ADMIN];
  }
}
