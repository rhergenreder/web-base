<?php

abstract class View {

  private $document;
  private $loadView;
  protected $searchable;
  protected $reference;
  protected $title;
  protected $langModules;

  public function __construct($document, $loadView = true) {
    $this->document = $document;
    $this->searchable = false;
    $this->printable = false;
    $this->reference = "";
    $this->title = "Untitled View";
    $this->langModules = array();
    $this->loadView = $loadView;
  }

  public function getTitle() { return $this->title; }
  public function __toString() { return $this->getCode(); }
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

  // TODO: do we need this in our general web-base?
  public function createFileIcon($mimeType) {
    $mimeType = htmlspecialchars($mimeType);
    return "<img src=\"/img/icons/admin/getIcon.php?mimeType=$mimeType\" class=\"file-icon\" alt=\"[$mimeType icon]\">";
  }

  public function createParagraph($title, $id, $content) {
    $id = replaceCssSelector($id);
    $iconId = urlencode("$id-icon");
    return "
      <div class=\"row\">
        <div class=\"col-12\">
          <i class=\"fas fa-link\" style=\"display:none;position:absolute\" id=\"$iconId\"></i>
          <h2 id=\"$id\" data-target=\"$iconId\" class=\"inlineLink\">$title</h2>
          <div class=\"margin-bottom-xl\"><hr>$content</div>
        </div>
      </div>";
  }

  public function createSimpleParagraph($content, $class="") {
    if($class) $class = " class=\"$class\"";
    return "<p$class>$content</p>";
  }

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

  public function createJumbotron($content, $lastModified=false) {
    $lastModified = ($lastModified ? "<span class=\"float-right text-xxs margin-top-xxxl\">Last modified: $lastModified</span>" : "");
    return "
      <div class=\"row\">
        <div class=\"col-12\">
          <div class=\"jumbotron\">
            $content
            $lastModified
          </div>
        </div>
      </div>";
  }

  protected function createLink($link, $title=null) {
    if(is_null($title)) $title=$link;
    return "<a href=\"$link\">$title</a>";
  }

  protected function createExternalLink($link, $title=null) {
    if(is_null($title)) $title=$link;
    return "<a href=\"$link\" target=\"_blank\" class=\"external\">$title</a>";
  }

  protected function createCodeBlock($code, $lang="") {
    if($lang) $lang = " class=\"$lang\"";
    $html = "<pre><code$lang>";
    $html .= intendCode($code);
    $html .= "</code></pre>";
    return $html;
  }

  protected function createIcon($icon, $margin = NULL) {
    $marginStr = (is_null($margin) ? "" : " margin-$margin");
    $iconClass = $this->getIconClass($icon);
    return "<i class=\"$iconClass$marginStr\"></i>";
  }

  protected function getIconClass($icon) {

    $mappings = array(
      "sign-out" => "sign-out-alt",
      "bank" => "university",
      "line-chart" => "chart-line",
      "circle-right" => "arrow-alt-circle-right",
      "refresh" => "sync"
    );

    if(isset($mappings[$icon]))
      $icon = $mappings[$icon];

    if($icon === "spinner")
      $icon .= " fa-spin";

    return "fas fa-$icon";
  }

  protected function createBootstrapTable($data) {
    $code = "<div class=\"container\">";
    foreach($data as $row) {
      $code .= "<div class=\"row margin-top-xs margin-bottom-xs\">";
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

            $content = (isset($col["content"]) ? $col["content"] : "");
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

  protected function createBash($command, $output="", $prefix="") {
    $command = htmlspecialchars($command);
    $output = htmlspecialchars($output);
    $output = str_replace("\n", "<br>", $output);
    return "<div class=\"bash\">
              <span>$prefix$</span>&nbsp;
              <span>$command</span><br>
              <span>$output</span>
            </div>";
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
};


?>
