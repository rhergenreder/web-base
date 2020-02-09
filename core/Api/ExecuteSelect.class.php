<?php

namespace Api;

use Api\Parameter\Parameter;
use Api\Parameter\StringType;

class ExecuteSelect extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array(
      'query' => new StringType('query')
    ));

    $this->isPublic = false;
    $this->variableParamCount = true;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $sql = $this->user->getSQL();
    $this->success = false;
    $this->result['rows'] = array();

    if(count($this->params) === 1) {
      $res = $sql->query($this->getParam('query'));
      if(!$res) {
        $this->lastError = 'Database Error: query() failed with ' . $sql->getLastError();
        return false;
      }

      while($row = $res->fetch_assoc()) {
        array_push($this->result['rows'], $row);
      }

      $this->success = true;
      $res->close();
    } else {
      $aSqlParams = array('');
      foreach($this->params as $param) {
        if($param->name === 'query') continue;

        $value = $param->value;
        switch($param->type) {
          case Parameter::TYPE_BOOLEAN:
            $value = $param->value ? 1 : 0;
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

        $aSqlParams[] = $value;
      }

      $tmp = array();
      foreach($aSqlParams as $key => $value) $tmp[$key] = &$aSqlParams[$key];
      if($stmt = $sql->connection->prepare($this->getParam('query'))) {
        if(call_user_func_array(array($stmt, "bind_param"), $tmp))
        {
          if($stmt->execute()) {
            $res = $stmt->get_result();
            if($res) {
              while($row = $res->fetch_assoc()) {
                array_push($this->result['rows'], $row);
              }
              $res->close();
              $this->success = true;
            } else {
              $this->lastError = 'Database Error: execute() failed with ' . $sql->getLastError();
            }
          } else {
            $this->lastError = 'Database Error: get_result() failed with ' . $sql->getLastError();
          }
        } else {
          $this->lastError = 'Database Error: bind_param() failed with ' . $sql->getLastError();
        }

        $stmt->close();
      } else {
        $this->lastError = 'Database Error: prepare failed with() ' . $sql->getLastError();
      }
    }

    return $this->success;
  }
};

?>
