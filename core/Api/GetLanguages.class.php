<?php

namespace Api;

class GetLanguages extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array());
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $query = 'SELECT uid, code, name FROM Language';
    $request = new ExecuteSelect($this->user);
    $this->success = $request->execute(array('query' => $query));
    $this->lastError = $request->getLastError();

    if($this->success) {
      $this->result['languages'] = array();
      if(count($request->getResult()['rows']) === 0) {
        $this->lastError = L("No languages found");
      } else {
        foreach($request->getResult()['rows'] as $row) {
          $this->result['languages'][$row['uid']] = $row;
        }
      }
    }

    return $this->success;
  }
};

?>
