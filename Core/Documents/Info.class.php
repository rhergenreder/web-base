<?php

namespace Core\Documents;

use Core\Elements\EmptyHead;
use Core\Elements\HtmlDocument;
use Core\Elements\SimpleBody;
use Core\Objects\DatabaseEntity\Group;
use Core\Objects\Router\Router;

class Info extends HtmlDocument {
  public function __construct(Router $router) {
    parent::__construct($router, EmptyHead::class, InfoBody::class);
    $this->searchable = false;
  }
}

class InfoBody extends SimpleBody {
  protected function getContent(): string {
    $user = $this->getContext()->getUser();
    if ($user && $user->hasGroup(Group::ADMIN)) {
      phpinfo();
      return "";
    } else {
      $message = "You are not logged in or do not have the proper privileges to access this page.";
      return $this->getDocument()->getRouter()->returnStatusCode(403, [ "message" => $message] );
    }
  }
}