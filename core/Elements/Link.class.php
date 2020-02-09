<?php

namespace Elements;

class Link extends Source {

  const STYLESHEET    = "stylesheet";
  const MIME_TEXT_CSS = "text/css";

  const FONTAWESOME               = '/css/fontawesome.min.css';
  // const JQUERY_UI                 = '/css/jquery-ui.css';
  // const JQUERY_TERMINAL           = '/css/jquery.terminal.min.css';
  const BOOTSTRAP                 = '/css/bootstrap.min.css';
  // const BOOTSTRAP_THEME           = '/css/bootstrap-theme.min.css';
  // const BOOTSTRAP_DATEPICKER_CSS  = '/css/bootstrap-datepicker.standalone.min.css';
  // const BOOTSTRAP_DATEPICKER3_CSS = '/css/bootstrap-datepicker.standalone.min.css';
  // const HIGHLIGHT                 = '/css/highlight.css';
  // const HIGHLIGHT_THEME           = '/css/theme.css';
  const CORE                      = "/css/style.css";
  // const ADMIN                     = "/css/admin.css";
  // const HOME                      = "/css/home.css";
  // const REVEALJS                  = "/css/reveal.css";
  // const REVEALJS_THEME_MOON       = "/css/reveal_moon.css";
  // const REVEALJS_THEME_BLACK      = "/css/reveal_black.css";

  private $type;
  private $rel;

  function __construct($rel, $href, $type = "") {
    parent::__construct('link', $href);
    $this->type = $type;
    $this->rel = $rel;
  }

  function getCode() {
    $type = (empty($this->type) ? "" : " type=\"$this->type\"");
    $link = "<link rel=\"$this->rel\" href=\"$this->url\" $type/>";
    return $link;
  }
}

?>
