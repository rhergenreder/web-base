<?php

namespace Core\Objects;

use Core\Driver\Logger\Logger;

class RateLimiting {

  private ?RateLimitRule $anonymousRule;
  private ?RateLimitRule $sessionRule;

  public function __construct(?RateLimitRule $anonymousRule = null, ?RateLimitRule $sessionRule = null) {
    $this->anonymousRule = $anonymousRule;
    $this->sessionRule = $sessionRule;
  }

  public function check(Context $context, string $method): bool {
    $session = $context->getSession();
    $logger = new Logger("RateLimiting", $context->getSQL());

    if ($session !== null) {
      // session based rate limiting
      $key = $session->getUUID();
      $effectiveRule = $this->sessionRule;
    } else {
      // ip-based rate limiting
      $key = $_SERVER['REMOTE_ADDR'];
      $effectiveRule = $this->anonymousRule;
    }

    if ($effectiveRule === null) {
      return true;
    }

    $redis = $context->getRedis();
    if (!$redis?->isConnected()) {
      $logger->error("Could not check rate limiting, redis is not connected.");
      return true;
    }

    $now = time();
    $queue = json_decode($redis->hGet($method, $key)) ?? [];
    $pass = true;
    $queueSize = count($queue);
    if ($queueSize >= $effectiveRule->getCount()) {
      // check the last n entries, whether they fit in the current window
      $requestsInWindow = 0;
      foreach ($queue as $accessTime) {
        if ($accessTime >= $now - $effectiveRule->getWindow()) {
          $requestsInWindow++;
          if ($requestsInWindow >= $effectiveRule->getCount()) {
            $pass = false;
            break;
          }
        } else {
          break;
        }
      }
    }

    if ($pass) {
      array_unshift($queue, $now);
      if ($queueSize + 1 > $effectiveRule->getCount()) {
        $queue = array_slice($queue, 0, $effectiveRule->getCount());
      }
      $redis->hSet($method, $key, json_encode($queue));
    }

    return $pass;
  }
}