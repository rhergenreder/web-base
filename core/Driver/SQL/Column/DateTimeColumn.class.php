<?php

namespace Driver\SQL\Column;

class DateTimeColumn extends Column {

  public function __construct($name, $nullable=false, $defaultValue=NULL) {
    parent::__construct($name, $nullable, $defaultValue);
  }
}