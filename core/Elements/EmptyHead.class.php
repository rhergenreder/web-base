<?php

namespace Elements;

class EmptyHead extends Head {

  public function __construct($document) {
    parent::__construct($document);
  }

  protected function initSources() {
  }

  protected function initMetas() {
    return array(
    );
  }

  protected function initRawFields() {
    return array();
  }

  protected function initTitle() {
    return "";
  }
}