<?php

namespace Documents;

use Elements\TemplateDocument;
use Objects\User;

class Document404 extends TemplateDocument {

  public function __construct(User $user) {
    parent::__construct($user, "404.twig");
  }

  public function loadParameters() {
    parent::loadParameters();
    http_response_code(404);
  }
}
