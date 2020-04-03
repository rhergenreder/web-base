<?php

namespace Views;

use Elements\View;

class View404 extends View {

  public function getCode() {
    return parent::getCode() . "<b>Not found</b>";
  }

};