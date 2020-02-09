<?php

namespace Api;

class Request {

  protected $user;
  protected $params;
  protected $lastError;
  protected $result;
  protected $success;
  protected $isPublic;
  protected $loginRequired;
  protected $variableParamCount;
  protected $isDisabled;
  protected $apiKeyAllowed;

  private $aDefaultParams;
  private $allowedMethods;
  private $externCall;

  public function __construct($user, $externCall = false, $params = array()) {
    $this->user = $user;
    $this->aDefaultParams = $params;
    $this->lastError = '';
    $this->success = false;
    $this->result = array();
    $this->externCall = $externCall;
    $this->isPublic = true;
    $this->isDisabled = false;
    $this->loginRequired = false;
    $this->variableParamCount = false;
    $this->apiKeyAllowed = true;
    $this->allowedMethods = array("GET", "POST");
  }

  protected function forbidMethod($method) {
    if (($key = array_search($method, $this->allowedMethods)) !== false) {
        unset($this->allowedMethods[$key]);
    }
  }

  public function getParamsString() {
    $str = "";
    $count = count($this->params);
    $i = 0;
    foreach($this->params as $param) {
      $str .= $param->toString();
      if($i < $count - 1) $str .= ", ";
      $i++;
    }

    return "($str)";
  }

  public function parseParams($values) {
    foreach($this->params as $name => $param) {
      $value = (isset($values[$name]) ? $values[$name] : NULL);

      if(!$param->optional && is_null($value)) {
        $this->lastError = 'Missing parameter: ' . $name;
        return false;
      }

      if(!is_null($value)) {
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

    if($this->externCall) {
      $values = $_REQUEST;
      if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER["CONTENT_TYPE"]) && in_array("application/json", explode(";", $_SERVER["CONTENT_TYPE"]))) {
        $jsonData = json_decode(file_get_contents('php://input'), true);
        $values = array_merge($values, $jsonData);
      }
    }

    if($this->isDisabled) {
      $this->lastError = "This function is currently disabled.";
      return false;
    }

    if($this->externCall && !$this->isPublic) {
      $this->lastError = 'This function is private.';
      header('HTTP 1.1 403 Forbidden');
      return false;
    }

    if(!in_array($_SERVER['REQUEST_METHOD'], $this->allowedMethods)) {
      $this->lastError = 'This method is not allowed';
      header('HTTP 1.1 405 Method Not Allowed');
      return false;
    }


    if($this->loginRequired) {
      $authorized = false;
      if(isset($values['api_key']) && $this->apiKeyAllowed) {
        $apiKey = $values['api_key'];
        $authorized = $this->user->authorize($apiKey);
      }

      if(!$this->user->isLoggedIn() && !$authorized) {
        $this->lastError = 'You are not logged in.';
        header('HTTP 1.1 401 Unauthorized');
        return false;
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

  protected function isValidString($str, $regex) {
    return preg_replace($regex, "", $str) === $str;
  }

  protected function createError($err) {
    $this->success = false;
    $this->lastError = $err;
    return false;
  }
  //
  // public static function callDirectly($class, $db) {
  //   header('Content-Type: application/json');
  //   require_once realpath($_SERVER['DOCUMENT_ROOT']) . '/php/api/objects/User.php';
  //   require_once realpath($_SERVER['DOCUMENT_ROOT']) . '/php/sql.php';
  //   require_once realpath($_SERVER['DOCUMENT_ROOT']) . '/php/conf/sql.php';
  //
  //   $sql = connectSQL(getSqlData($db));
  //   $user = new CUser($sql);
  //   $request = new $class($user, true);
  //   $request->execute();
  //   $sql->close();
  //   $user->sendCookies();
  //   return $request->getJsonResult();
  // }

  protected function getParam($name) { return isset($this->params[$name]) ? $this->params[$name]->value : NULL; }
  public function isPublic() { return $this->isPublic; }
  public function getDescription() { return ''; }
  public function getSection() { return 'Default'; }
  public function getLastError() { return $this->lastError; }
  public function getResult() { return $this->result; }
  public function success() { return $this->success; }
  public function loginRequired() { return $this->loginRequired; }
  public function isExternCall() { return $this->externCall; }

  public function getJsonResult() {
    $this->result['success'] = $this->success;
    $this->result['msg'] = $this->lastError;
    return json_encode($this->result);
  }
};


?>
