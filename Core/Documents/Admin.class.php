<?php

namespace Core\Documents;

use Core\Elements\TemplateDocument;
use Core\Objects\Router\Router;

class Admin extends TemplateDocument {
  public function __construct(Router $router) {
    $user = $router->getContext()->getUser();
    $template = $user ? "admin.twig" : "redirect.twig";
    $params = $user ? [] : ["url" => "/login"];
    $this->title = "Administration";
    $this->searchable = false;
    parent::__construct($router, $template, $params);
    $this->enableCSP();
  }
}