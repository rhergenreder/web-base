<?php


namespace Views\Admin;

use Elements\Document;
use Elements\View;

class AdminView extends View {

  protected array $errorMessages;

  public function __construct(Document $document) {
    parent::__construct($document);
    $this->errorMessages = array();
  }

  public function getErrorMessages() {
    return $this->errorMessages;
  }

  public function getCode() {
    $html = parent::getCode();

    $home = L("Home");

    $html .=
      "<div class=\"content-header\">
        <div class=\"container-fluid\">
          <div class=\"row mb-2\">
            <div class=\"col-sm-6\">
              <h1 class=\"m-0 text-dark\">$this->title</h1>
            </div>
            <div class=\"col-sm-6\">
              <ol class=\"breadcrumb float-sm-right\">
                <li class=\"breadcrumb-item\"><a href=\"/\">$home</a></li>
                <li class=\"breadcrumb-item active\">$this->title</li>
              </ol>
            </div>
          </div>
        </div><!-- /.container-fluid -->
      </div>";

    return $html;
  }
}