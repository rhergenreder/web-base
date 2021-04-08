<?php


namespace Driver\SQL\Type;


use Driver\SQL\Column\Column;

class CurrentColumn extends Column {

  public function __construct(string $string) {
    parent::__construct($string);
  }
}