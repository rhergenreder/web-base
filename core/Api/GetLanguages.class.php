<?php

namespace Api;

class GetLanguages extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array());
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $sql = $this->user->getSQL();
    $res = $sql->select("uid", "code", "name")
      ->from("Language")
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if($this->success) {
      $this->result['languages'] = array();
      if(empty($res) === 0) {
        $this->lastError = L("No languages found");
      } else {
        foreach($res as $row) {
          $this->result['languages'][$row['uid']] = $row;
        }
      }
    }

    return $this->success;
  }
}