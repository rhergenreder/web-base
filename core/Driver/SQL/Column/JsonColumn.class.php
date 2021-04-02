<?php

namespace Driver\SQL\Column;

class JsonColumn extends Column {

  public function __construct(string $name, bool $nullable = false, $defaultValue = null) {
    parent::__construct($name, $nullable, $defaultValue);
  }

}