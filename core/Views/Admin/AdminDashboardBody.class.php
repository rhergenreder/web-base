<?php

namespace Views\Admin;

use Elements\Body;
use Elements\Script;

class AdminDashboardBody extends Body {

  public function __construct($document) {
    parent::__construct($document);
  }

  public function getCode(): string {
    $html = parent::getCode();
    $script = new Script(Script::MIME_TEXT_JAVASCRIPT, "/js/admin.min.js");
    $html .= "<body><div class=\"wrapper\" id=\"root\">$script</div></body>";
    return $html;
  }
}
