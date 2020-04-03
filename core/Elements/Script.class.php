<?php

namespace Elements;

class Script extends \View {

  const MIME_TEXT_JAVASCRIPT    = "text/javascript";

  const CORE                    = "/js/script.js";
  // const HOME                    = "/js/home.js";
  const ADMIN                   = "/js/admin.js";
  // const SORTTABLE               = "/js/sorttable.js";
  const JQUERY                  = "/js/jquery.min.js";
  // const JQUERY_UI               = "/js/jquery-ui.js";
  // const JQUERY_MASKED_INPUT     = "/js/jquery.maskedinput.min.js";
  // const JQUERY_CONTEXT_MENU     = "/js/jquery.contextmenu.min.js";
  // const JQUERY_TERMINAL         = "/js/jquery.terminal.min.js";
  // const JQUERY_TERMINAL_UNIX    = "/js/unix_formatting.js";
  // const JSCOLOR                 = "/js/jscolor.min.js";
  // const SYNTAX_HIGHLIGHTER      = "/js/syntaxhighlighter.js";
  // const HIGHLIGHT               = "/js/highlight.pack.js";
  // const GOOGLE_CHARTS           = "/js/loader.js";
  // const BOOTSTRAP               = "/js/bootstrap.min.js";
  // const BOOTSTRAP_DATEPICKER_JS = "/js/bootstrap-datepicker.min.js";
  // const POPPER                  = "/js/popper.min.js";
  // const JSMPEG                  = "/js/jsmpeg.min.js";
  // const MOMENT                  = "/js/moment.min.js";
  // const CHART                   = "/js/chart.js";
  // const REVEALJS                = "/js/reveal.js";
  // const REVEALJS_PLUGIN_NOTES   = "/js/reveal_notes.js";
  const INSTALL                 = "/js/install.js";
  const BOOTSTRAP = "/js/bootstrap.bundle.min.js";

  const HIGHLIGHT_JS_LOADER = "\$(document).ready(function(){\$('code').each(function(i, block) { hljs.highlightBlock(block); }); })";
  const ADMINLTE = "/js/adminlte.min.js";

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