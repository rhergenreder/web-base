<?php


namespace Core\Driver\SQL\Type;


use Core\Driver\SQL\Column\Column;

class CurrentColumn extends Column {

  public function __construct(string $name) {
    parent::__construct($name);
  }
}