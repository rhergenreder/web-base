<?php

namespace Elements;

class Style extends StaticView {

  private string $style;

  function __construct($style) {
    $this->style = $style;
  }

  function getCode() {
    return "<style>$this->style</style>";
  }
}
