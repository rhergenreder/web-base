<?php

namespace Elements;

class Link extends StaticView {

  const STYLESHEET    = "stylesheet";
  const MIME_TEXT_CSS = "text/css";

  const FONTAWESOME = "/css/fontawesome.min.css";
  const BOOTSTRAP   = "/css/bootstrap.min.css";
  const CORE        = "/css/style.css";
  const ACCOUNT       = "/css/account.css";

  private string $type;
  private string $rel;
  private string $href;

  function __construct($rel, $href, $type = "") {
    $this->href = $href;
    $this->type = $type;
    $this->rel = $rel;
  }

  function getCode(): string {
    $type = (empty($this->type) ? "" : " type=\"$this->type\"");
    return "<link rel=\"$this->rel\" href=\"$this->href\"$type/>";
  }
}
