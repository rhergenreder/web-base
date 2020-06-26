<?php

namespace Api;

use Objects\User;

class Request {

  protected User $user;
  protected array $params;
  protected string $lastError;
  protected array $result;
  protected bool $success;
  protected bool $isPublic;
  protected bool $loginRequired;
  protected bool $variableParamCount;
  protected bool $isDisabled;
  protected bool $apiKeyAllowed;
  protected array $requiredGroup;
  protected bool $csrfTokenRequired;

  private array $aDefaultParams;
  private array $allowedMethods;
  private bool $externalCall;

  public function __construct(User $user, bool $externalCall = false, array $params = array()) {
    $this->user = $user;
    $this->aDefaultParams = $params;

    $this->success = false;
    $this->result = array();
    $this->externalCall = $externalCall;
    $this->isPublic = true;
    $this->isDisabled = false;
    $this->loginRequired = false;
    $this->variableParamCount = false;
    $this->apiKeyAllowed = true;
    $this->allowedMethods = array("GET", "POST");
    $this->requiredGroup = array();
    $this->lastError = "";
    $this->csrfTokenRequired = true;
  }

  protected function forbidMethod($method) {
    if (($key = array_search($method, $this->allowedMethods)) !== false) {
        unset($this->allowedMethods[$key]);
    }
  }

  public function parseParams($values) {

    foreach($this->params as $name => $param) {
      $value = $values[$name] ?? NULL;

      $isEmpty = (is_string($value) || is_array($value)) && empty($value);
      if(!$param->optional && (is_null($value) || $isEmpty)) {
        $this->lastError = 'Missing parameter: ' . $name;
        return false;
      }

      if(!is_null($value) && !$isEmpty) {
        if(!$param->parseParam($value)) {
          $value = print_r($value, true);
          $this->lastError = "Invalid Type for parameter: $name '$value' (Required: " . $param->getTypeName() . ")";
          return false;
        }
      }
    }
    return true;
  }

  public function parseVariableParams($values) {
    foreach($values as $name => $value) {
      if(isset($this->params[$name])) continue;
      $type = Parameter\Parameter::parseType($value);
      $param = new Parameter\Parameter($name, $type, true);
      $param->parseParam($value);
      $this->params[$name] = $param;
    }
  }

  public function execute($values = array()) {
    $this->params = $this->aDefaultParams;
    $this->success = false;
    $this->result = array();
    $this->lastError = '';

    if($this->user->isLoggedIn()) {
      $this->result['logoutIn'] = $this->user->getSession()->getExpiresSeconds();
    }

    if($this->externalCall) {
      $values = $_REQUEST;
      if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER["CONTENT_TYPE"]) && in_array("application/json", explode(";", $_SERVER["CONTENT_TYPE"]))) {
        $jsonData = json_decode(file_get_contents('php://input'), true);
        if ($jsonData) {
          $values = array_merge($values, $jsonData);
        } else {
          $this->lastError = 'Invalid request body.';
          header('HTTP 1.1 400 Bad Request');
          return false;
        }
      }
    }

    if($this->isDisabled) {
      $this->lastError = "This function is currently disabled.";
      return false;
    }

    if($this->externalCall && !$this->isPublic) {
      $this->lastError = 'This function is private.';
      header('HTTP 1.1 403 Forbidden');
      return false;
    }

    if(!in_array($_SERVER['REQUEST_METHOD'], $this->allowedMethods)) {
      $this->lastError = 'This method is not allowed';
      header('HTTP 1.1 405 Method Not Allowed');
      return false;
    }

    // TODO: Check this!
    if($this->externalCall) {
      $apiKeyAuthorized = false;

      // Logged in or api key authorized?
      if ($this->loginRequired) {
        if(isset($values['api_key']) && $this->apiKeyAllowed) {
          $apiKey = $values['api_key'];
          $apiKeyAuthorized = $this->user->authorize($apiKey);
        }

        if(!$this->user->isLoggedIn() && !$apiKeyAuthorized) {
          $this->lastError = 'You are not logged in.';
          header('HTTP 1.1 401 Unauthorized');
          return false;
        }
      }

      // CSRF Token
      if($this->csrfTokenRequired && !$apiKeyAuthorized) {
        // csrf token required + external call
        // if it's not a call with API_KEY, check for csrf_token
        if (!isset($values["csrf_token"]) || strcmp($values["csrf_token"], $this->user->getSession()->getCsrfToken()) !== 0) {
          $this->lastError = "CSRF-Token mismatch";
          header('HTTP 1.1 403 Forbidden');
          return false;
        }
      }

      // Check for permission
      if (!($this instanceof PermissionAPI)) {
        $req = new \Api\Permission\Check($this->user);
        $this->success = $req->execute(array("method" => $this->getMethod()));
        $this->lastError = $req->getLastError();
        if (!$this->success) {
          header('HTTP 1.1 401 Unauthorized');
          return false;
        }
      }
    }

    if(!$this->parseParams($values))
      return false;

    if($this->variableParamCount)
      $this->parseVariableParams($values);

    if(!$this->user->getSQL()->isConnected()) {
      $this->lastError = $this->user->getSQL()->getLastError();
      return false;
    }

    $this->user->getSQL()->setLastError('');
    $this->success = true;
    return true;
  }

  protected function createError($err) {
    $this->success = false;
    $this->lastError = $err;
    return false;
  }

  protected function getParam($name) {
    return isset($this->params[$name]) ? $this->params[$name]->value : NULL;
  }

  public function isPublic() { return $this->isPublic; }
  public function getLastError() { return $this->lastError; }
  public function getResult() { return $this->result; }
  public function success() { return $this->success; }
  public function loginRequired() { return $this->loginRequired; }
  public function isExternalCall() { return $this->externalCall; }

  private function getMethod() {
    $class = str_replace("\\", "/", get_class($this));
    $class = substr($class, strlen("api/"));
    return $class;
  }

  public function getJsonResult() {
    $this->result['success'] = $this->success;
    $this->result['msg'] = $this->lastError;
    return json_encode($this->result);
  }
}