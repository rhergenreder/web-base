<?php

use Api\Request;
use Configuration\Configuration;
use Objects\User;

function __new_header_impl(string $line) {
  if (preg_match("/^HTTP\/([0-9.]+) (\d+) (.*)$/", $line, $m)) {
    RequestTest::$SENT_STATUS_CODE = intval($m[2]);
    return;
  }

  $key = $line;
  $value = "";
  $index = strpos($key, ": ");
  if ($index !== false) {
    $key = substr($line, 0, $index);
    $value = substr($line, $index + 2);
  }

  RequestTest::$SENT_HEADERS[$key] = $value;
}

function __new_http_response_code_impl(int $code) {
  RequestTest::$SENT_STATUS_CODE = $code;
}

function __new_die_impl($content) {
  RequestTest::$SENT_CONTENT = $content;
}

class RequestTest extends \PHPUnit\Framework\TestCase {

  const FUNCTION_OVERRIDES = ["header", "http_response_code"];
  static User $USER;
  static User $USER_LOGGED_IN;

  static ?string $SENT_CONTENT;
  static array $SENT_HEADERS;
  static ?int $SENT_STATUS_CODE;

  public static function setUpBeforeClass(): void {

    $config = new Configuration();
    RequestTest::$USER = new User($config);
    RequestTest::$USER_LOGGED_IN = new User($config);
    if (!RequestTest::$USER->getSQL() || !RequestTest::$USER->getSQL()->isConnected()) {
      throw new Exception("Could not establish database connection");
    } else {
      RequestTest::$USER->setLanguage(\Objects\Language::DEFAULT_LANGUAGE());
    }

    if (!function_exists("runkit7_function_rename") || !function_exists("runkit7_function_remove")) {
      throw new Exception("Request Unit Test requires runkit7 extension");
    }

    if (ini_get("runkit.internal_override") !== "1") {
      throw new Exception("Request Unit Test requires runkit7 with internal_override enabled to function properly");
    }

    foreach (self::FUNCTION_OVERRIDES as $functionName) {
      runkit7_function_rename($functionName, "__orig_${functionName}_impl");
      runkit7_function_rename("__new_${functionName}_impl", $functionName);
    }
  }

  public static function tearDownAfterClass(): void {
    RequestTest::$USER->getSQL()->close();
    foreach (self::FUNCTION_OVERRIDES as $functionName) {
      runkit7_function_remove($functionName);
      runkit7_function_rename("__orig_${functionName}_impl", $functionName);
    }
  }

  private function simulateRequest(Request $request, string $method, array $get = [], array $post = [], array $headers = []): bool {

    if (!is_cli()) {
      self::throwException(new \Exception("Cannot simulate request outside cli"));
    }

    $_SERVER = [];
    $_SERVER["REQUEST_METHOD"] = $method;
    self::$SENT_HEADERS = [];
    self::$SENT_STATUS_CODE = null;
    self::$SENT_CONTENT = null;

    foreach ($headers as $key => $value) {
      $key = "HTTP_" . preg_replace("/\s/", "_", strtoupper($key));
      $_SERVER[$key] = $value;
    }

    $_GET = $get;
    $_POST = $post;

    return $request->execute();
  }

  public function testAllMethods() {
    // all methods allowed
    $allMethodsAllowed = new RequestAllMethods(RequestTest::$USER, true);
    $this->assertTrue($this->simulateRequest($allMethodsAllowed, "GET"), $allMethodsAllowed->getLastError());
    $this->assertTrue($this->simulateRequest($allMethodsAllowed, "POST"), $allMethodsAllowed->getLastError());
    $this->assertFalse($this->simulateRequest($allMethodsAllowed, "PUT"), $allMethodsAllowed->getLastError());
    $this->assertFalse($this->simulateRequest($allMethodsAllowed, "DELETE"), $allMethodsAllowed->getLastError());
    $this->assertTrue($this->simulateRequest($allMethodsAllowed, "OPTIONS"), $allMethodsAllowed->getLastError());
    $this->assertEquals(204, self::$SENT_STATUS_CODE);
    $this->assertEquals(["Allow" => "OPTIONS, GET, POST"], self::$SENT_HEADERS);
  }

  public function testOnlyPost() {
    // only post allowed
    $onlyPostAllowed = new RequestOnlyPost(RequestTest::$USER, true);
    $this->assertFalse($this->simulateRequest($onlyPostAllowed, "GET"));
    $this->assertEquals("This method is not allowed", $onlyPostAllowed->getLastError(), $onlyPostAllowed->getLastError());
    $this->assertEquals(405, self::$SENT_STATUS_CODE);
    $this->assertTrue($this->simulateRequest($onlyPostAllowed, "POST"), $onlyPostAllowed->getLastError());
    $this->assertTrue($this->simulateRequest($onlyPostAllowed, "OPTIONS"), $onlyPostAllowed->getLastError());
    $this->assertEquals(204, self::$SENT_STATUS_CODE);
    $this->assertEquals(["Allow" => "OPTIONS, POST"], self::$SENT_HEADERS);
  }

  public function testPrivate() {
    // private method
    $privateExternal = new RequestPrivate(RequestTest::$USER, true);
    $this->assertFalse($this->simulateRequest($privateExternal, "GET"));
    $this->assertEquals("This function is private.", $privateExternal->getLastError());
    $this->assertEquals(403, self::$SENT_STATUS_CODE);

    $privateInternal = new RequestPrivate(RequestTest::$USER, false);
    $this->assertTrue($privateInternal->execute());
  }

  public function testDisabled() {
    // disabled method
    $disabledMethod = new RequestDisabled(RequestTest::$USER, true);
    $this->assertFalse($this->simulateRequest($disabledMethod, "GET"));
    $this->assertEquals("This function is currently disabled.", $disabledMethod->getLastError(), $disabledMethod->getLastError());
    $this->assertEquals(503, self::$SENT_STATUS_CODE);
  }

  public function testLoginRequired() {
    $loginRequired = new RequestLoginRequired(RequestTest::$USER, true);
    $this->assertFalse($this->simulateRequest($loginRequired, "GET"));
    $this->assertEquals("You are not logged in.", $loginRequired->getLastError(), $loginRequired->getLastError());
    $this->assertEquals(401, self::$SENT_STATUS_CODE);
  }
}

abstract class TestRequest extends Request {
  public function __construct(User $user, bool $externalCall = false, $params = []) {
    parent::__construct($user, $externalCall, $params);
  }

  protected function _die(string $data = ""): bool {
    __new_die_impl($data);
    return true;
  }

  protected function _execute(): bool {
    return true;
  }
}

class RequestAllMethods extends TestRequest {
  public function __construct(User $user, bool $externalCall = false) {
    parent::__construct($user, $externalCall, []);
  }
}

class RequestOnlyPost extends TestRequest {
  public function __construct(User $user, bool $externalCall = false) {
    parent::__construct($user, $externalCall, []);
    $this->forbidMethod("GET");
  }
}

class RequestPrivate extends TestRequest {
  public function __construct(User $user, bool $externalCall = false) {
    parent::__construct($user, $externalCall, []);
    $this->isPublic = false;
  }
}

class RequestDisabled extends TestRequest {
  public function __construct(User $user, bool $externalCall = false) {
    parent::__construct($user, $externalCall, []);
    $this->isDisabled = true;
  }
}

class RequestLoginRequired extends TestRequest {
  public function __construct(User $user, bool $externalCall = false) {
    parent::__construct($user, $externalCall, []);
    $this->loginRequired = true;
  }
}