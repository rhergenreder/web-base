<?php

namespace Core\Elements;

abstract class StaticView {

  public abstract function getCode();

  public function __toString() {
    return $this->getCode();
  }

}