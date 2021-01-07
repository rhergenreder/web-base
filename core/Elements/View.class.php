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

  protected function load(string $viewClass) : string {
    try {
      $reflectionClass = new \ReflectionClass($viewClass);
      if ($reflectionClass->isSubclassOf(View::class) && $reflectionClass->isInstantiable()) {
        $view = $reflectionClass->newInstanceArgs(array($this->getDocument()));
        $view->loadView();
        return $view;
      }
    } catch(\ReflectionException $e) {
      error_log($e->getMessage());
    }

    return "";
  }

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

  protected function createLink($link, $title=null, $classes="") {
    if(is_null($title)) $title=$link;
    if(!empty($classes)) $classes = " class=\"$classes\"";
    return "<a href=\"$link\"$classes>$title</a>";
  }

  protected function createExternalLink($link, $title=null) {
    if(is_null($title)) $title=$link;
    return "<a href=\"$link\" target=\"_blank\" class=\"external\">$title</a>";
  }

  protected function createIcon($icon, $type = "fas", $classes = "") {
    $iconClass = "$type fa-$icon";

    if($icon === "spinner" || $icon === "circle-notch")
      $iconClass .= " fa-spin";

    if($classes)
      $iconClass .= " $classes";

    return "<i class=\"$iconClass\" ></i>";
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

  protected function createStatusText($type, $text, $id="", $hidden=false, $classes="") {
    if(strlen($id) > 0) $id = " id=\"$id\"";
    if($hidden) $classes .= " hidden";
    if(strlen($classes) > 0) $classes = " $classes";
    return "<div class=\"alert alert-$type$hidden$classes\" role=\"alert\"$id>$text</div>";
  }

  protected function createBadge($type, $text) {
    $text = htmlspecialchars($text);
    return "<span class=\"badge badge-$type\">$text</span>";
  }

  protected function createJumbotron(string $content, bool $fluid=false, $class="") {
    $jumbotronClass = "jumbotron" . ($fluid ? "-fluid" : "");
    if (!empty($class)) $jumbotronClass .= " $class";

    return "
      <div class=\"row\">
        <div class=\"col-12\">
          <div class=\"$jumbotronClass\">
            $content
          </div>
        </div>
      </div>";
  }

  public function createSimpleParagraph(string $content, string $class="") {
    if($class) $class = " class=\"$class\"";
    return "<p$class>$content</p>";
  }

  public function createParagraph($title, $id, $content) {
    $id = replaceCssSelector($id);
    $iconId = urlencode("$id-icon");
    return "
      <div class=\"row mt-4\">
        <div class=\"col-12\">
          <h2 id=\"$id\" data-target=\"$iconId\" class=\"inlineLink\">$title</h2>
          <hr/>
          $content
        </div>
      </div>";
  }

  protected function createBootstrapTable($data, string $classes="") {
    $classes = empty($classes) ? "" : " $classes";
    $code = "<div class=\"container$classes\">";
    foreach($data as $row) {
      $code .= "<div class=\"row mt-2 mb-2\">";
      $columnCount = count($row);
      if($columnCount > 0) {
        $remainingSize = 12;
        $columnSize = 12 / $columnCount;
        foreach($row as $col) {
          $size = ($columnSize <= $remainingSize ? $columnSize : $remainingSize);
          $content = $col;
          $class = "";
          $code .= "<div";

          if(is_array($col)) {
            $content = "";
            foreach($col as $key => $val) {
              if(strcmp($key, "content") === 0) {
                $content = $val;
              } else if(strcmp($key, "class") === 0) {
                $class = " " . $col["class"];
              } else if(strcmp($key, "cols") === 0 && is_numeric($val)) {
                $size = intval($val);
              } else {
                $code .= " $key=\"$val\"";
              }
            }

            if(isset($col["class"])) $class = " " . $col["class"];
          }

          if($size <= 6) $class .= " col-md-" . intval($size * 2);
          $code .= " class=\"col-lg-$size$class\">$content</div>";
          $remainingSize -= $size;
        }
      }
      $code .= "</div>";
    }

    $code .= "</div>";
    return $code;
  }
}
