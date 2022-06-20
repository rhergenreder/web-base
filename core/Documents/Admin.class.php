<?php

namespace Documents;

use Elements\TemplateDocument;
use Objects\Router\Router;

class Admin extends TemplateDocument {
  public function __construct(Router $router) {
    $user = $router->getContext()->getUser();
    $template = $user ? "admin.twig" : "redirect.twig";
    $params = $user ? [] : ["url" => "/login"];
    parent::__construct($router, $template, $params);
    $this->enableCSP();
  }
}