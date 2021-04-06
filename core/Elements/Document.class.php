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
    $this->user = $user;
    $this->head = new $headClass($this);
    $this->body = new $bodyClass($this);
    $this->databaseRequired = true;
    $this->activeView = $view;
  }

  public function getHead(): Head { return $this->head; }
  public function getBody(): Body { return $this->body; }
  public function getSQL(): ?\Driver\SQL\SQL { return $this->user->getSQL(); }
  public function getUser(): User { return $this->user; }

  public function getView() : ?View {

    if ($this->activeView === null) {
      return null;
    }

    $view = parseClass($this->activeView);
    $file = getClassPath($view);
    if(!file_exists($file) || !is_subclass_of($view, View::class)) {
      return null;
    }

    return new $view($this);
  }

  public function getRequestedView(): string {
    return $this->activeView;
  }

  function getCode(): string {

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
    $lang = $this->user->getLanguage()->getShortCode();

    $html = "<!DOCTYPE html>";
    $html .= "<html lang=\"$lang\">";
    $html .= $head;
    $html .= $body;
    $html .= "</html>";
    return $html;
  }

}