<?php

namespace Elements;

class Style extends StaticView {

  private string $style;

  function __construct($style) {
    $this->style = $style;
  }

  function getCode(): string {
    return "<style>$this->style</style>";
  }
}
