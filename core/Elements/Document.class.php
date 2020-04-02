<?php

namespace Elements;

abstract class Document {

  protected $head;
  protected $body;
  protected $user;
  protected $databaseRequired;

  public function __construct($user, $headClass, $bodyClass) {
    $this->head = new $headClass($this);
    $this->body = new $bodyClass($this);
    $this->user = $user;
    $this->databaseRequired = true;
  }

  public function getHead() { return $this->head; }
  public function getBody() { return $this->body; }
  public function getSQL()  { return $this->user->getSQL(); }
  public function getUser() { return $this->user; }

  protected function sendHeaders() {
    header("X-Frame-Options: DENY");
  }

  public static function createSearchableDocument($documentClass, $user) {
    return new $documentClass($user);
  }

  public static function createDocument($class) {
    // TODO: check instance, configuration, ..

    require_once realpath($_SERVER['DOCUMENT_ROOT']) . '/php/sql.php';
    // require_once realpath($_SERVER['DOCUMENT_ROOT']) . '/php/conf/config.php';
    // require_once realpath($_SERVER['DOCUMENT_ROOT']) . "/php/pages/$file.php";
    require_once realpath($_SERVER['DOCUMENT_ROOT']) . '/php/api/objects/User.php';

    $connectionData = getSqlData($database);
    $sql = connectSQL($connectionData);
    if(!$sql->isConnected()) {
      http_response_code(500);
      die('Internal Database error');
    }

    $user = new CUser($sql);
    $document = new $class($user);
    $code = $document->getCode();

    $document->sendHeaders();
    $user->sendCookies();
    die($code);
  }

  function getCode() {

    if ($this->databaseRequired) {
      $sql = $this->user->getSQL();
      if (is_null($sql)) {
        die("Database is not configured yet.");
      } else if(!$sql->isConnected()) {
        die("Database is not connected: " . $sql->getLastError());
      }
    }

    $body = $this->body->getCode();
    $head = $this->head->getCode();

    $html = "<!DOCTYPE html>";
    $html .= "<html>";
    $html .= $head;
    $html .= $body;
    $html .= "</html>";
    return $html;
  }

};

?>
