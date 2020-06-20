<?php


namespace Views\Account;


use Elements\Document;
use Elements\View;

class ConfirmEmail extends View {

  public function __construct(Document $document, $loadView = true) {
    parent::__construct($document, $loadView);
  }

  public function getCode() {
    $html = parent::getCode();

    return $html;
  }
}