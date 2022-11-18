<?php

namespace Core\Elements;

class Style extends StaticView {

  private string $style;

  function __construct($style) {
    $this->style = $style;
  }

  function getCode(): string {
    // TODO: do we need to escape the content here?
    return html_tag("style", [], $this->style, false);
  }
}
