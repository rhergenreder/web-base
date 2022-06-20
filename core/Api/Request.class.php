<?php

namespace Api;

use Driver\Logger\Logger;
use Objects\Context;
use PhpMqtt\Client\MqttClient;

/**
 * TODO: we need following features, probably as abstract/generic class/method:
 * - easy way for pagination (select with limit/offset)
 * - CRUD Endpoints/Objects (Create, Update, Delete)
 */

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

    $this->success = false;
    $this->result = array();
    $this->externalCall = $externalCall;
    $this->isPublic = true;
    $this->isDisabled = false;
    $this->loginRequired = false;
    $this->variableParamCount = false;
    $this->apiKeyAllowed = true;
    $this->allowedMethods = array("GET", "POST");
    $this->lastError = "";
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

  protected function forbidMethod($method) {
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

  protected function allowMethod($method) {
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

      $isEmpty = (is_string($value) && strlen($value) === 0) || (is_array($value) && empty($value));
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

  public function parseVariableParams($values) {
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
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER["CONTENT_TYPE"]) && in_array("application/json", explode(";", $_SERVER["CONTENT_TYPE"]))) {
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
        } else if ($session) {
          $tfaToken = $session->getUser()->getTwoFactorToken();
          if ($tfaToken && $tfaToken->isConfirmed() && !$tfaToken->isAuthenticated()) {
            $this->lastError = '2FA-Authorization is required';
            http_response_code(401);
            return false;
          }
        }
      }

      // CSRF Token
      if ($this->csrfTokenRequired && $session) {
        // csrf token required + external call
        // if it's not a call with API_KEY, check for csrf_token
        $csrfToken = $values["csrf_token"] ?? $_SERVER["HTTP_XSRF_TOKEN"] ?? null;
        if (!$csrfToken || strcmp($csrfToken, $session->getCsrfToken()) !== 0) {
          $this->lastError = "CSRF-Token mismatch";
          http_response_code(403);
          return false;
        }
      }

      // Check for permission
      if (!($this instanceof \Api\Permission\Save)) {
        $req = new \Api\Permission\Check($this->context);
        $this->success = $req->execute(array("method" => $this->getMethod()));
        $this->lastError = $req->getLastError();
        if (!$this->success) {
          return false;
        }
      }
    }

    if (!$this->parseParams($values)) {
      return false;
    }

    if ($this->variableParamCount) {
      $this->parseVariableParams($values);
    }

    $sql = $this->context->getSQL();
    if (!$sql->isConnected()) {
      $this->lastError = $sql->getLastError();
      return false;
    }

    $this->success = true;
    $success = $this->_execute();
    if ($this->success !== $success) {
      // _execute returns a different value then it set for $this->success
      // this should actually not occur, how to handle this case?
      $this->success = $success;
    }

    $sql->setLastError('');
    return $this->success;
  }

  protected function createError($err): bool {
    $this->success = false;
    $this->lastError = $err;
    return false;
  }

  protected function getParam($name, $obj = NULL) {
    // i don't know why phpstorm
    if ($obj === NULL) {
      $obj = $this->params;
    }

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

  private function getMethod() {
    $class = str_replace("\\", "/", get_class($this));
    $class = substr($class, strlen("api/"));
    return $class;
  }

  public function getJsonResult(): string {
    $this->result['success'] = $this->success;
    $this->result['msg'] = $this->lastError;
    return json_encode($this->result);
  }

  protected function disableOutputBuffer() {
    ob_implicit_flush(true);
    $levels = ob_get_level();
    for ( $i = 0; $i < $levels; $i ++ ) {
      ob_end_flush();
    }
    flush();
  }

  protected function disableCache() {
    header("Last-Modified: " . (new \DateTime())->format("D, d M Y H:i:s T"));
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
  }

  protected function setupSSE() {
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
  protected function startMqttSSE(MqttClient $mqtt, callable $onPing) {
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

  protected function processImageUpload(string $uploadDir, array $allowedExtensions = ["jpg","jpeg","png","gif"], $transformCallback = null) {
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
}