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
  private ?string $nonce;

  function __construct($rel, $href, $type = "") {
    $this->href = $href;
    $this->type = $type;
    $this->rel = $rel;
    $this->nonce = null;
  }

  function getCode(): string {
    $attributes = ["rel" => $this->rel, "href" => $this->href];

    if (!empty($this->type)) {
      $attributes["type"] = $this->type;
    }
    if (!empty($this->nonce)) {
      $attributes["nonce"] = $this->nonce;
    }

    return html_tag_short("link", $attributes);
  }

  public function setNonce(string $nonce) {
    $this->nonce = $nonce;
  }
}
