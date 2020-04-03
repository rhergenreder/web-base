<?php

namespace Elements;

class Script extends StaticView {

  const MIME_TEXT_JAVASCRIPT    = "text/javascript";

  const CORE      = "/js/script.js";
  const ADMIN     = "/js/admin.js";
  const JQUERY    = "/js/jquery.min.js";
  const INSTALL   = "/js/install.js";
  const BOOTSTRAP = "/js/bootstrap.bundle.min.js";
  const ADMINLTE  = "/js/adminlte.min.js";

  private string $type;
  private string $content;
  private string $src;

  function __construct($type, $src, $content = "") {
    $this->src = $src;
    $this->type = $type;
    $this->content = $content;
  }

  function getCode() {
    $src = (empty($this->src) ? "" : " src=\"$this->src\"");
      return "<script type=\"$this->type\"$src>$this->content</script>";
  }
}