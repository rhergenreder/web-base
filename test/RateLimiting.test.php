<?php

use Core\Objects\Context;

function __new_time_impl() {
  return RateLimitingTest::$CURRENT_TIME;
}

class RateLimitingTest extends \PHPUnit\Framework\TestCase {

  const FUNCTION_OVERRIDES = ["time"];

  static int $CURRENT_TIME;

  public static function setUpBeforeClass(): void {

    if (!function_exists("runkit7_function_rename") || !function_exists("runkit7_function_remove")) {
      throw new Exception("Request Unit Test requires runkit7 extension");
    }

    if (ini_get("runkit.internal_override") !== "1") {
      throw new Exception("Request Unit Test requires runkit7 with internal_override enabled to function properly");
    }

    self::$CURRENT_TIME = time();
    foreach (self::FUNCTION_OVERRIDES as $functionName) {
      runkit7_function_rename($functionName, "__orig_{$functionName}_impl");
      runkit7_function_rename("__new_{$functionName}_impl", $functionName);
    }
  }

  public function testRules() {
    $secondsRule = new \Core\Objects\RateLimitRule(1, 1, \Core\Objects\RateLimitRule::SECOND);
    $this->assertEquals(1, $secondsRule->getCount());
    $this->assertEquals(1, $secondsRule->getWindow());

    $secondsRule2 = new \Core\Objects\RateLimitRule(5, 120, \Core\Objects\RateLimitRule::SECOND);
    $this->assertEquals(5, $secondsRule2->getCount());
    $this->assertEquals(120, $secondsRule2->getWindow());

    $minuteRule = new \Core\Objects\RateLimitRule(10, 5, \Core\Objects\RateLimitRule::MINUTE);
    $this->assertEquals(10, $minuteRule->getCount());
    $this->assertEquals(5 * 60, $minuteRule->getWindow());

    $hourRule = new \Core\Objects\RateLimitRule(15, 4, \Core\Objects\RateLimitRule::HOUR);
    $this->assertEquals(15, $hourRule->getCount());
    $this->assertEquals(4 * 60 * 60, $hourRule->getWindow());

    // should be interpreted as 30 seconds, ignoring invalid unit
    $invalidUnitRule = new \Core\Objects\RateLimitRule(20, 30,  10);
    $this->assertEquals(20, $invalidUnitRule->getCount());
    $this->assertEquals(30, $invalidUnitRule->getWindow());
  }

  public function testRateLimiting() {

    $testContext = new TestContext();
    $redis = $testContext->getRedis();
    $this->assertTrue($redis->isConnected());

    $_SERVER["REMOTE_ADDR"] = "0.0.0.0";
    $method = "test/method";
    $sessionUUID = uuidv4();
    $windowSize = 10;

    // should pass
    $noRateLimiting = new \Core\Objects\RateLimiting();
    for ($i = 0; $i < 100; $i++) {
      $this->assertTrue($noRateLimiting->check($testContext, $method));
    }

    $anonymousRateLimiting = new \Core\Objects\RateLimiting(
      new \Core\Objects\RateLimitRule($windowSize, 5, \Core\Objects\RateLimitRule::SECOND)
    );

    for ($i = 0; $i < $windowSize; $i++) {
      $this->assertTrue($anonymousRateLimiting->check($testContext, $method));
      $this->assertCount($i + 1, json_decode($redis->hGet($method, $_SERVER["REMOTE_ADDR"])));
    }
    $this->assertFalse($anonymousRateLimiting->check($testContext, $method));
    self::$CURRENT_TIME += 4;
    $this->assertFalse($anonymousRateLimiting->check($testContext, $method));
    self::$CURRENT_TIME += 2;
    $this->assertTrue($anonymousRateLimiting->check($testContext, $method));
    $this->assertCount($windowSize, json_decode($redis->hGet($method, $_SERVER["REMOTE_ADDR"])));

    $testContext->setSession($sessionUUID);
    for ($i = 0; $i < 100; $i++) {
      $this->assertTrue($anonymousRateLimiting->check($testContext, $method));
    }

    $sessionBasedRateLimiting = new \Core\Objects\RateLimiting(
      null,
      new \Core\Objects\RateLimitRule($windowSize, 5, \Core\Objects\RateLimitRule::SECOND)
    );

    for ($i = 0; $i < $windowSize; $i++) {
      $this->assertTrue($sessionBasedRateLimiting->check($testContext, $method));
      $this->assertCount($i + 1, json_decode($redis->hGet($method, $sessionUUID)));
    }

    $this->assertFalse($sessionBasedRateLimiting->check($testContext, $method));
    self::$CURRENT_TIME += 10;
    $this->assertTrue($sessionBasedRateLimiting->check($testContext, $method));
    $testContext->destroySession();
    for ($i = 0; $i < 100; $i++) {
      $this->assertTrue($sessionBasedRateLimiting->check($testContext, $method));
    }
  }
}

class TestSession extends \Core\Objects\DatabaseEntity\Session {
  public function __construct(Context $context, string $uuid) {
    parent::__construct($context, new \Core\Objects\DatabaseEntity\User());
    $this->uuid = $uuid;
  }
}

class TestContext extends Context {

  public function __construct() {
    parent::__construct();
    $this->redis = new TestRedisConnection();
  }

  public function getRedis(): ?\Core\Driver\Redis\RedisConnection {
    return $this->redis;
  }

  public function setSession(string $sessionUUID): void {
    $this->session = new TestSession($this, $sessionUUID);
  }

  public function destroySession(): void {
    $this->session = null;
  }
}

class TestRedisConnection extends \Core\Driver\Redis\RedisConnection {

  private array $redisData;

  public function __construct() {
    parent::__construct(null);
    $this->getLogger()->unitTestMode();
    $this->redisData = [];
  }

  public function connect(\Core\Objects\ConnectionData $connectionData): bool {
    return true;
  }

  public function isConnected(): bool {
    return true;
  }

  public function hSet(string $hashKey, mixed $key, string $value): bool {
    if (!isset($this->redisData[$hashKey])) {
      $this->redisData[$hashKey] = [];
    }

    $this->redisData[$hashKey][$key] = $value;
    return true;
  }

  public function hGet(string $hashKey, string $key): ?string {
    if (!isset($this->redisData[$hashKey])) {
      return "";
    }

    return $this->redisData[$hashKey][$key] ?? "";
  }
}