<?php

namespace Elements;

abstract class SimpleBody extends Body {

  public function __construct($document) {
    parent::__construct($document);
  }

  public function getCode(): string {
    $content = $this->getContent();
    return parent::getCode() . "<body>$content</body>";
  }

  protected abstract function getContent(): string;
}