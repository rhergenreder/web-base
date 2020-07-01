<?php


namespace Views\Account;


use Elements\Document;
use Elements\View;

class ResetPassword extends View {

  public function __construct(Document $document, $loadView = true) {
    parent::__construct($document, $loadView);
  }

  public function getCode() {
    $html = parent::getCode();

    return $html;
  }
}