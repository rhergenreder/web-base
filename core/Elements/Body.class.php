<?php

namespace Elements;

use View;

abstract class Body extends View {
  public function __construct($document) {
    parent::__construct($document);
  }
}