<?php

namespace Core\Elements;

class Script extends StaticView {

  const MIME_TEXT_JAVASCRIPT    = "text/javascript";

  const CORE      = "/js/script.js";
  const JQUERY    = "/js/jquery.min.js";
  const INSTALL   = "/js/install.js";
  const BOOTSTRAP = "/js/bootstrap.bundle.min.js";
  const ACCOUNT   = "/js/account.js";
  const FONTAWESOME = "/js/fontawesome-all.min.js";

  private string $type;
  private string $content;
  private string $src;
  private ?string $nonce;

  function __construct($type, $src, $content = "") {
    $this->src = $src;
    $this->type = $type;
    $this->content = $content;
    $this->nonce = null;
  }

  function getCode(): string {
    $attributes = ["type" => $this->type];
    if (!empty($this->src)) {
      $attributes["src"] = $this->src;
    }

    if (!empty($this->nonce)) {
      $attributes["nonce"] = $this->nonce;
    }

    // TODO: do we need to escape the content here?
    return html_tag("script", $attributes, $this->content, false);
  }

  public function setNonce(string $nonce) {
    $this->nonce = $nonce;
  }
}