<?php


namespace Views\Admin;

use Elements\Document;

class Dashboard extends AdminView {

  public function __construct(Document $document) {
    parent::__construct($document);
  }

  public function loadView() {
    parent::loadView();
    $this->title = L("Dashboard");
  }

  public function getCode() {
    $html = parent::getCode();

    return $html;
  }

}