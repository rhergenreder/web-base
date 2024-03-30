<?php

namespace Core\API;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Query\Insert;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\TwoFactorToken;
use Core\Objects\TwoFactor\KeyBasedTwoFactorToken;
use PhpMqtt\Client\MqttClient;

abstract class Request {

  protected Context $context;
  protected Logger $logger;
  protected array $params;
  protected string $lastError;
  protected array $result;
  protected bool $success;
  protected bool $isPublic;
  protected bool $loginRequired;
  protected bool $variableParamCount;
  protected bool $isDisabled;
  protected bool $apiKeyAllowed;
  protected bool $csrfTokenRequired;

  private array $defaultParams;
  private array $allowedMethods;
  private bool $externalCall;

  public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
    $this->context = $context;
    $this->logger = new Logger($this->getAPIName(), $this->context->getSQL());
    $this->defaultParams = $params;
    $this->externalCall = $externalCall;
    $this->variableParamCount = false;

    // result
    $this->lastError = "";
    $this->success = false;
    $this->result = array();

    // restrictions
    $this->isPublic = true;
    $this->isDisabled = false;
    $this->loginRequired = false;
    $this->apiKeyAllowed = true;
    $this->allowedMethods = array("GET", "POST");
    $this->csrfTokenRequired = true;
  }

  public function getAPIName(): string {
    if (get_class($this) === Request::class) {
      return "API";
    }

    $reflection = new \ReflectionClass($this);
    if ($reflection->getParentClass()->isAbstract() && $reflection->getParentClass()->isSubclassOf(Request::class)) {
      return $reflection->getParentClass()->getShortName() . "/" . $reflection->getShortName();
    } else {
      return $reflection->getShortName();
    }
  }

  protected function forbidMethod($method): void {
    if (($key = array_search($method, $this->allowedMethods)) !== false) {
      unset($this->allowedMethods[$key]);
    }
  }

  public function getDefaultParams(): array {
    return $this->defaultParams;
  }

  public function isDisabled(): bool {
    return $this->isDisabled;
  }

  protected function allowMethod($method): void {
    $availableMethods = ["GET", "HEAD", "POST", "PUT", "DELETE", "PATCH", "TRACE", "CONNECT"];
    if (in_array($method, $availableMethods) && !in_array($method, $this->allowedMethods)) {
      $this->allowedMethods[] = $method;
    }
  }

  protected function getRequestMethod() {
    return $_SERVER["REQUEST_METHOD"];
  }

  public function parseParams($values, $structure = NULL): bool {

    if ($structure === NULL) {
      $structure = $this->params;
    }

    foreach ($structure as $name => $param) {
      $value = $values[$name] ?? NULL;

      $isEmpty = is_string($value) && strlen($value) === 0;
      if (!$param->optional && (is_null($value) || $isEmpty)) {
        return $this->createError("Missing parameter: $name");
      }

      $param->reset();
      if (!is_null($value) && !$isEmpty) {
        if (!$param->parseParam($value)) {
          $value = print_r($value, true);
          return $this->createError("Invalid Type for parameter: $name '$value' (Required: " . $param->getTypeName() . ")");
        }
      }
    }

    return true;
  }

  public function parseVariableParams($values): void {
    foreach ($values as $name => $value) {
      if (isset($this->params[$name])) continue;
      $type = Parameter\Parameter::parseType($value);
      $param = new Parameter\Parameter($name, $type, true);
      $param->parseParam($value);
      $this->params[$name] = $param;
    }
  }

  // wrapper for unit tests
  protected function _die(string $data = ""): bool {
    die($data);
  }

  protected abstract function _execute(): bool;
  public static function getDefaultACL(Insert $insert): void { }

  protected function check2FA(?TwoFactorToken $tfaToken = null): bool {

    // do not require 2FA for verifying endpoints
    if ($this instanceof \Core\API\Tfa\VerifyTotp || $this instanceof \Core\API\Tfa\VerifyKey) {
      return true;
    }

    if ($tfaToken === null) {
      $tfaToken = $this->context->getUser()?->getTwoFactorToken();
    }

    if ($tfaToken && $tfaToken->isConfirmed() && !$tfaToken->isAuthenticated()) {

      if ($tfaToken instanceof KeyBasedTwoFactorToken && !$tfaToken->hasChallenge()) {
        $tfaToken->generateChallenge();
      }

      $this->lastError = '2FA-Authorization is required';
      $this->result["twoFactorToken"] = $tfaToken->jsonSerialize([
        "type", "challenge", "authenticated", "confirmed", "credentialID"
      ]);
      return false;
    }

    return true;
  }

  public final function execute($values = array()): bool {

    $this->params = array_merge([], $this->defaultParams);
    $this->success = false;
    $this->result = array();
    $this->lastError = '';

    $session = $this->context->getSession();
    if ($session) {
      $this->result['logoutIn'] = $session->getExpiresSeconds();
    }

    if ($this->externalCall) {
      $values = $_REQUEST;
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array("application/json", explode(";", $_SERVER["CONTENT_TYPE"] ?? ""))) {
        $jsonData = json_decode(file_get_contents('php://input'), true);
        if ($jsonData !== null) {
          $values = array_merge($values, $jsonData);
        } else {
          $this->lastError = 'Invalid request body.';
          http_response_code(400);
          return false;
        }
      }
    }

    if ($this->isDisabled) {
      $this->lastError = "This function is currently disabled.";
      http_response_code(503);
      return false;
    }

    if ($this->externalCall && !$this->isPublic) {
      $this->lastError = 'This function is private.';
      http_response_code(403);
      return false;
    }

    if ($this->externalCall) {

      if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); # No content
        header("Allow: OPTIONS, " . implode(", ", $this->allowedMethods));
        return $this->_die();
      }

      // check the request method
      if (!in_array($_SERVER['REQUEST_METHOD'], $this->allowedMethods)) {
        $this->lastError = 'This method is not allowed';
        http_response_code(405);
        return false;
      }

      $apiKeyAuthorized = false;
      if (!$session && $this->apiKeyAllowed) {
        if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
          $authHeader = $_SERVER["HTTP_AUTHORIZATION"];
          if (startsWith($authHeader, "Bearer ")) {
            $apiKey = substr($authHeader, strlen("Bearer "));
            $apiKeyAuthorized = $this->context->loadApiKey($apiKey);
          }
        }
      }

      // Logged in or api key authorized?
      if ($this->loginRequired) {
        if (!$session && !$apiKeyAuthorized) {
          $this->lastError = 'You are not logged in.';
          http_response_code(401);
          return false;
        } else if ($session && !$this->check2FA()) {
          http_response_code(401);
          return false;
        }
      }

      // CSRF Token
      if ($this->csrfTokenRequired && $session) {
        // csrf token required + external call
        // if it's not a call with API_KEY, check for csrfToken
        $csrfToken = $values["csrfToken"] ?? $_SERVER["HTTP_XSRF_TOKEN"] ?? null;
        if (!$csrfToken || strcmp($csrfToken, $session->getCsrfToken()) !== 0) {
          $this->lastError = "CSRF-Token mismatch";
          http_response_code(403);
          return false;
        }
      }

      // Check for permission
      $req = new \Core\API\Permission\Check($this->context);
      $this->success = $req->execute(array("method" => self::getEndpoint()));
      $this->lastError = $req->getLastError();
      if (!$this->success) {
        return false;
      }
    }

    if (!$this->parseParams($values)) {
      return false;
    }

    if ($this->variableParamCount) {
      $this->parseVariableParams($values);
    }

    $sql = $this->context->getSQL();
    if ($sql === null || !$sql->isConnected()) {
      $this->lastError = $sql ? $sql->getLastError() : "Database not connected yet.";
      return false;
    }

    $this->success = true;
    try {
      $success = $this->_execute();
      if ($this->success !== $success) {
        // _execute might return a different value then it set for $this->success
        // this should actually not occur, how to handle this case?
        $this->success = $success;
      }
    } catch (\Error $err) {
      http_response_code(500);
      $this->createError($err->getMessage());
      $this->logger->error($err->getMessage());
    }

    $sql->setLastError("");
    return $this->success;
  }

  protected function createError($err): bool {
    $this->success = false;
    $this->lastError = $err;
    return false;
  }

  protected function getParam($name, $obj = NULL): mixed {
    if ($obj === NULL) {
      $obj = $this->params;
    }

    // I don't know why phpstorm
    return (isset($obj[$name]) ? $obj[$name]->value : NULL);
  }

  public function isMethodAllowed(string $method): bool {
    return in_array($method, $this->allowedMethods);
  }

  public function isPublic(): bool {
    return $this->isPublic;
  }

  public function getLastError(): string {
    return $this->lastError;
  }

  public function getResult(): array {
    return $this->result;
  }

  public function success(): bool {
    return $this->success;
  }

  public function loginRequired(): bool {
    return $this->loginRequired;
  }

  public function isExternalCall(): bool {
    return $this->externalCall;
  }

  public static function getEndpoint(string $prefix = ""): ?string {
    $reflectionClass = new \ReflectionClass(get_called_class());
    if ($reflectionClass->isAbstract()) {
      return null;
    }

    $isNestedAPI = $reflectionClass->getParentClass()->getName() !== Request::class;
    if (!$isNestedAPI) {
      # e.g. /api/stats or /api/info
      $methodName = $reflectionClass->getShortName();
      return $prefix . lcfirst($methodName);
    } else {
      # e.g. /api/user/login
      $methodClass = $reflectionClass;
      $nestedClass = $reflectionClass->getParentClass();
      while (!endsWith($nestedClass->getName(), "API")) {
        $methodClass = $nestedClass;
        $nestedClass = $nestedClass->getParentClass();
      }

      $nestedAPI = substr(lcfirst($nestedClass->getShortName()), 0, -3);
      $methodName = lcfirst($methodClass->getShortName());
      return $prefix . $nestedAPI . "/" . $methodName;
    }
  }

  public function getJsonResult(): string {
    $this->result['success'] = $this->success;
    $this->result['msg'] = $this->lastError;
    return json_encode($this->result);
  }

  protected function disableOutputBuffer(): void {
    ob_implicit_flush(true);
    $levels = ob_get_level();
    for ( $i = 0; $i < $levels; $i ++ ) {
      ob_end_flush();
    }
    flush();
  }

  protected function disableCache(): void {
    header("Last-Modified: " . (new \DateTime())->format("D, d M Y H:i:s T"));
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
  }

  protected function setupSSE(): void {
    $this->context->sendCookies();
    $this->context->getSQL()?->close();
    set_time_limit(0);
    ignore_user_abort(true);
    header('Content-Type: text/event-stream');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    $this->disableCache();
    $this->disableOutputBuffer();
  }

  /**
   * @throws \PhpMqtt\Client\Exceptions\ProtocolViolationException
   * @throws \PhpMqtt\Client\Exceptions\DataTransferException
   * @throws \PhpMqtt\Client\Exceptions\MqttClientException
   */
  protected function startMqttSSE(MqttClient $mqtt, callable $onPing): void {
    $lastPing = 0;
    $mqtt->registerLoopEventHandler(function(MqttClient $mqtt, $elapsed) use (&$lastPing, $onPing) {
      if ($elapsed - $lastPing >= 5) {
        $onPing();
        $lastPing = $elapsed;
      }

      if (connection_status() !== 0) {
        $mqtt->interrupt();
      }
    });

    $mqtt->loop();
    $this->lastError = "MQTT Loop disconnected";
    $mqtt->disconnect();
  }

  protected function processImageUpload(string $uploadDir, array $allowedExtensions = ["jpg","jpeg","png","gif"], $transformCallback = null): bool|array {
    if (empty($_FILES)) {
      return $this->createError("You need to upload an image.");
    } else if (count($_FILES) > 1) {
      return $this->createError("You can only upload one image at once.");
    }

    $upload = array_values($_FILES)[0];
    if (is_array($upload["name"])) {
      return $this->createError("You can only upload one image at once.");
    } else if ($upload["error"] !== UPLOAD_ERR_OK) {
      return $this->createError("There was an error uploading the image, code: " . $upload["error"]);
    }

    $imageName = $upload["name"];
    $ext = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
      return $this->createError("Only the following file extensions are allowed: " . implode(",", $allowedExtensions));
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
      return $this->createError("Upload directory does not exist and could not be created.");
    }

    $srcPath = $upload["tmp_name"];
    $mimeType = mime_content_type($srcPath);
    if (!startsWith($mimeType, "image/")) {
      return $this->createError("Uploaded file is not an image.");
    }

    try {
      $image = new \Imagick($srcPath);

      // strip exif
      $profiles = $image->getImageProfiles("icc", true);
      $image->stripImage();
      if (!empty($profiles)) {
        $image->profileImage("icc", $profiles['icc']);
      }
    } catch (\ImagickException $ex) {
      return $this->createError("Error loading image: " . $ex->getMessage());
    }

    try {
      if ($transformCallback) {
        $fileName = call_user_func([$this, $transformCallback], $image, $uploadDir);
      } else {

        $image->writeImage($srcPath);
        $image->destroy();

        $uuid = uuidv4();
        $fileName = "$uuid.$ext";
        $destPath = "$uploadDir/$fileName";
        if (!file_exists($destPath)) {
          if (!@move_uploaded_file($srcPath, $destPath)) {
            return $this->createError("Could not store uploaded file.");
          }
        }
      }

      return [$fileName, $imageName];
    } catch (\ImagickException $ex) {
      return $this->createError("Error processing image: " . $ex->getMessage());
    }
  }

  protected function getFileUpload(string $name, bool $allowMultiple = false, ?array $extensions = null): false|array {
    if (!isset($_FILES[$name]) || (is_array($_FILES[$name]["name"]) && empty($_FILES[$name]["name"])) || empty($_FILES[$name]["name"])) {
      return $this->createError("Missing form-field '$name'");
    }

    $files = [];
    if (is_array($_FILES[$name]["name"])) {
      $numFiles = count($_FILES[$name]["name"]);
      if (!$allowMultiple && $numFiles > 1) {
        return $this->createError("Only one file allowed for form-field '$name'");
      } else {
        for ($i = 0; $i < $numFiles; $i++) {
          $fileName = $_FILES[$name]["name"][$i];
          $filePath = $_FILES[$name]["tmp_name"][$i];
          $files[$fileName] = $filePath;

          if (!empty($extensions) && !in_array(pathinfo($fileName, PATHINFO_EXTENSION), $extensions)) {
            return $this->createError("File '$fileName' has forbidden extension, allowed: " . implode(",", $extensions));
          }
        }
      }
    } else {
      $fileName = $_FILES[$name]["name"];
      $filePath = $_FILES[$name]["tmp_name"];
      $files[$fileName] = $filePath;
      if (!empty($extensions) && !in_array(pathinfo($fileName, PATHINFO_EXTENSION), $extensions)) {
        return $this->createError("File '$fileName' has forbidden extension, allowed: " . implode(",", $extensions));
      }
    }

    if ($allowMultiple) {
      return $files;
    } else {
      $fileName = key($files);
      return [$fileName, $files[$fileName]];
    }
  }

  public static function getApiEndpoints(): array {

    // first load all direct classes
    $classes = [];
    $apiDirs = ["Core", "Site"];
    foreach ($apiDirs as $apiDir) {
      $basePath = realpath(WEBROOT . "/$apiDir/API/");
      if (!$basePath) {
        continue;
      }

      foreach (scandir($basePath) as $fileName) {
        $fullPath = $basePath . "/" . $fileName;
        if (is_file($fullPath) && endsWith($fileName, ".class.php")) {
          require_once $fullPath;
          $apiName = explode(".", $fileName)[0];
          $className = "\\$apiDir\\API\\$apiName";
          if (!class_exists($className)) {
            continue;
          }

          $reflectionClass = new \ReflectionClass($className);
          if (!$reflectionClass->isSubclassOf(Request::class) || $reflectionClass->isAbstract()) {
            continue;
          }

          $endpoint = "$className::getEndpoint"();
          $classes[$endpoint] = $reflectionClass;
        }
      }
    }

    // then load all inheriting classes
    foreach (get_declared_classes() as $declaredClass) {
      $reflectionClass = new \ReflectionClass($declaredClass);
      if (!$reflectionClass->isAbstract() && $reflectionClass->isSubclassOf(Request::class)) {
        $className = $reflectionClass->getName();
        $endpoint = "$className::getEndpoint"();
        $classes[$endpoint] = $reflectionClass;
      }
    }

    return $classes;
  }
}