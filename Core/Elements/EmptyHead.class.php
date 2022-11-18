<?php

namespace Core\Elements;

class EmptyHead extends Head {

  public function __construct($document) {
    parent::__construct($document);
  }

  protected function initSources() {
  }

  protected function initMetas(): array {
    return array(
    );
  }

  protected function initRawFields(): array {
    return array();
  }

  protected function initTitle(): string {
    return "";
  }
}