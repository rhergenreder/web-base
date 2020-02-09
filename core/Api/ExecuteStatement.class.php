<?php

namespace Api;

use Api\Parameter\Parameter;
use Api\Parameter\StringType;

class ExecuteStatement extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array(
      'query' => new StringType('query')
    ));

    $this->isPublic = false;
    $this->variableParamCount = true;
  }

  public function execute($aValues = array()) {
    if(!parent::execute($aValues)) {
      return false;
    }

    $this->success = false;
    $this->result['rows'] = array();

    if(count($this->params) == 1) {
      $this->success = $this->user->getSQL()->execute($this->getParam('query'));
      if(!$this->success) {
        $this->lastError = $this->user->getSQL()->getLastError();
      }
    } else {
      $aSqlParams = array('');
      foreach($this->params as $param) {
        if($param->name === 'query') continue;

        $value = $param->value;
        if(is_null($value)) {
          $aSqlParams[0] .= 's';
        } else {
          switch($param->type) {
            case Parameter::TYPE_BOOLEAN:
              $value = $param->value ? 1 : 0;
              $aSqlParams[0] .= 'i';
              break;
            case Parameter::TYPE_INT:
              $aSqlParams[0] .= 'i';
              break;
            case Parameter::TYPE_FLOAT:
              $aSqlParams[0] .= 'd';
              break;
            case Parameter::TYPE_DATE:
              $value = $value->format('Y-m-d');
              $aSqlParams[0] .= 's';
              break;
            case Parameter::TYPE_TIME:
              $value = $value->format('H:i:s');
              $aSqlParams[0] .= 's';
              break;
            case Parameter::TYPE_DATE_TIME:
              $value = $value->format('Y-m-d H:i:s');
              $aSqlParams[0] .= 's';
              break;
            case Parameter::TYPE_EMAIL:
            default:
              $aSqlParams[0] .= 's';
          }
        }

        $aSqlParams[] = $value;
      }

      $tmp = array();
      foreach($aSqlParams as $key => $value) $tmp[$key] = &$aSqlParams[$key];
      if($stmt = $this->user->getSQL()->connection->prepare($this->getParam('query'))) {
        if(call_user_func_array(array($stmt, "bind_param"), $tmp)) {
          if($stmt->execute()) {
            $this->result['rows'] = $stmt->affected_rows;
            $this->success = true;
          } else {
            $this->lastError = 'Database Error: execute() failed with ' . $this->user->getSQL()->getLastError();
          }
        } else {
          $this->lastError = 'Database Error: bind_param() failed with ' . $this->user->getSQL()->getLastError();
        }

        $stmt->close();
      } else {
        $this->lastError = 'Database Error: prepare() failed with ' . $this->user->getSQL()->getLastError();
      }
    }

    return $this->success;
  }
};

?>
