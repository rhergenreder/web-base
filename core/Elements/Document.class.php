<?php

namespace Elements;

use Objects\User;

abstract class Document {

  protected Head $head;
  protected Body $body;
  protected User $user;
  protected bool $databaseRequired;
  private ?string $activeView;

  public function __construct(User $user, $headClass, $bodyClass, ?string $view = NULL) {
    $this->head = new $headClass($this);
    $this->body = new $bodyClass($this);
    $this->user = $user;
    $this->databaseRequired = true;
    $this->activeView = $view;
  }

  public function getHead() { return $this->head; }
  public function getBody() { return $this->body; }
  public function getSQL()  { return $this->user->getSQL(); }
  public function getUser() { return $this->user; }
  public function getView() { return $this->activeView; }

  protected function sendHeaders() {
    header("X-Frame-Options: DENY");
  }

  public static function createSearchableDocument($documentClass, $user) {
    return new $documentClass($user);
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

}