<?php

namespace Core\Documents;

use Core\Elements\TemplateDocument;
use Core\Objects\Router\Router;

class Admin extends TemplateDocument {
  public function __construct(Router $router) {
    parent::__construct($router, "admin.twig", []);
    $this->title = "Administration";
    $this->searchable = false;
    $this->enableCSP();
    $this->addCSPWhitelist("/react/dist/admin-panel/");
  }
}