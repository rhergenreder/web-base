<?php

namespace Elements;

abstract class View extends StaticView {

  private Document $document;
  private bool $loadView;
  protected bool $searchable;
  protected string $reference;
  protected string $title;
  protected array $langModules;

  public function __construct(Document $document, $loadView = true) {
    $this->document = $document;
    $this->searchable = false;
    $this->reference = "";
    $this->title = "Untitled View";
    $this->langModules = array();
    $this->loadView = $loadView;
  }

  public function getTitle() { return $this->title; }
  public function getDocument() { return $this->document; }
  public function isSearchable() { return $this->searchable; }
  public function getReference() { return $this->reference; }

  private function loadLanguageModules() {
    $lang = $this->document->getUser()->getLanguage();
    foreach($this->langModules as $langModule) {
      $lang->loadModule($langModule);
    }
  }

  // Virtual Methods
  public function loadView() { }

  public function getCode() {

    // Load translations
    $this->loadLanguageModules();

    // Load Meta Data + Head (title, scripts, includes, ...)
    if($this->loadView) {
      $this->loadView();
    }

    return '';
  }

  // UI Functions
  private function createList($items, $tag) {
    if(count($items) === 0)
      return "<$tag></$tag>";
    else
      return "<$tag><li>" . implode("</li><li>", $items) . "</li></$tag>";
  }

  public function createOrderedList($items=array()) {
    return $this->createList($items, "ol");
  }

  public function createUnorderedList($items=array()) {
    return $this->createList($items, "ul");
  }

  protected function createLink($link, $title=null) {
    if(is_null($title)) $title=$link;
    return "<a href=\"$link\">$title</a>";
  }

  protected function createExternalLink($link, $title=null) {
    if(is_null($title)) $title=$link;
    return "<a href=\"$link\" target=\"_blank\" class=\"external\">$title</a>";
  }

  protected function createIcon($icon, $type = "fas", $classes = "") {
    $iconClass = "$type fa-$icon";

    if($icon === "spinner")
      $iconClass .= " fa-spin";

    if($classes)
      $iconClass .= " $classes";

    return "<i class=\"$iconClass\"></i>";
  }

  protected function createErrorText($text, $id="", $hidden=false) {
    return $this->createStatusText("danger", $text, $id, $hidden);
  }

  protected function createWarningText($text, $id="", $hidden=false) {
    return $this->createStatusText("warning", $text, $id, $hidden);
  }

  protected function createSuccessText($text, $id="", $hidden=false) {
    return $this->createStatusText("success", $text, $id, $hidden);
  }

  protected function createSecondaryText($text, $id="", $hidden=false) {
    return $this->createStatusText("secondary", $text, $id, $hidden);
  }

  protected function createInfoText($text, $id="", $hidden=false) {
    return $this->createStatusText("info", $text, $id, $hidden);
  }

  protected function createStatusText($type, $text, $id="", $hidden=false) {
    if(strlen($id) > 0) $id = " id=\"$id\"";
    $hidden = ($hidden?" hidden" : "");
    return "<div class=\"alert alert-$type$hidden\" role=\"alert\"$id>$text</div>";
  }

  protected function createBadge($type, $text) {
    $text = htmlspecialchars($text);
    return "<span class=\"badge badge-$type\">$text</span>";
  }
}