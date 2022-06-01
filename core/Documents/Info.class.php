<?php

namespace Documents;

use Elements\EmptyHead;
use Elements\HtmlDocument;
use Elements\SimpleBody;
use Objects\Router\Router;

class Info extends HtmlDocument {
  public function __construct(Router $router) {
    parent::__construct($router, EmptyHead::class, InfoBody::class);
  }
}

class InfoBody extends SimpleBody {
  protected function getContent(): string {
    $user = $this->getDocument()->getUser();
    if ($user->isLoggedIn() && $user->hasGroup(USER_GROUP_ADMIN)) {
      phpinfo();
    } else {
      $message = "You are not logged in or do not have the proper privileges to access this page.";
      return $this->getDocument()->getRouter()->returnStatusCode(403, [ "message" => $message] );
    }
  }
}