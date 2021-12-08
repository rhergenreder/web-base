<?php

namespace Documents;

use Elements\TemplateDocument;
use Objects\User;

class Admin extends TemplateDocument {
  public function __construct(User $user) {
    $template = $user->isLoggedIn() ? "admin.twig" : "redirect.twig";
    $params = $user->isLoggedIn() ? [] : ["url" => "/login"];
    parent::__construct($user, $template, $params);
    $this->enableCSP();
  }
}