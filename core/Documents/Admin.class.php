<?php

namespace Documents;

use Elements\TemplateDocument;
use Objects\Router\Router;

class Admin extends TemplateDocument {
  public function __construct(Router $router) {
    $user = $router->getUser();
    $template = $user->isLoggedIn() ? "admin.twig" : "redirect.twig";
    $params = $user->isLoggedIn() ? [] : ["url" => "/login"];
    parent::__construct($router, $template, $params);
    $this->enableCSP();
  }
}