<?php

namespace Views;

// Source: https://adminlte.io/themes/v3/

class Admin extends \View {
  public function __construct($document) {
    parent::__construct($document);
  }

  private function getMainHeader() {
    $home = L("Home");
    $search = L("Search");

    $iconMenu = $this->createIcon("bars");
    $iconSearch = $this->createIcon("search");
    $iconNotifications = $this->createIcon("bell");
    $header = "";

    return $header;
  }

  private function getMainContent() {
    return "";
  }

  private function getSideBar() {
    return "";
  }

  public function getCode() {
    $html = parent::getCode();

    $html .= "<div class=\"main-wrapper\">";
    $html .=    $this->getMainHeader();
    $html .=    "<div id=\"content\">";
    $html .=      $this->getSideBar();
    $html .=      $this->getMainContent();
    $html .=    "</div>
             </div>";

    return $html;
  }
}

?>
