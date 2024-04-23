<?php

namespace Core\Objects;

class RateLimitRule {

  const SECOND = 0;
  const MINUTE = 1;
  const HOUR = 2;

  private int $count;
  private int $perSecond;

  public function __construct(int $count, int $time, int $unit) {
    $this->count = $count;
    $this->perSecond = $time;
    if ($unit === self::HOUR) {
      $this->perSecond *= 60 * 60;
    } else if ($unit === self::MINUTE) {
      $this->perSecond *= 60;
    }
  }

  public function getCount(): int {
    return $this->count;
  }

  public function getWindow(): int {
    return $this->perSecond;
  }
}