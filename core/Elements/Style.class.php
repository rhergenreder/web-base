<?php

namespace Elements;

class Style extends Source {

  private $style;

  function __construct($style) {
    parent::__construct('style', '');
    $this->style = $style;
  }

  function getCode() {
    return "<style>$this->style</style>";
  }
}

?>
