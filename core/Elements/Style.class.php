<?php

namespace Elements;

use View;

class Style extends View {

  private string $style;

  function __construct($style) {
    $this->style = $style;
  }

  function getCode() {
    return "<style>$this->style</style>";
  }
}
