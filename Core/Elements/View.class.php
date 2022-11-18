<?php

namespace Core\Elements;

abstract class View extends StaticView {

  private Document $document;
  private bool $loadView;
  protected string $title;
  protected array $langModules;

  public function __construct(Document $document, bool $loadView = true) {
    $this->document = $document;
    $this->title = "Untitled View";
    $this->langModules = array();
    $this->loadView = $loadView;
  }

  public function getTitle(): string { return $this->title; }
  public function getDocument(): Document { return $this->document; }

  public function getSiteName(): string {
    return $this->getDocument()->getSettings()->getSiteName();
  }

  protected function load(string $viewClass) : string {
    try {
      $reflectionClass = new \ReflectionClass($viewClass);
      if ($reflectionClass->isSubclassOf(View::class) && $reflectionClass->isInstantiable()) {
        $view = $reflectionClass->newInstanceArgs(array($this->getDocument()));
        $view->loadView();
        return $view;
      }
    } catch(\ReflectionException $e) {
      $this->document->getLogger()->error("Error loading view: '$viewClass': " . $e->getMessage());
    }

    return "";
  }

  private function loadLanguageModules() {
    $lang = $this->document->getContext()->getLanguage();
    foreach ($this->langModules as $langModule) {
      $lang->loadModule($langModule);
    }
  }

  // Virtual Methods
  public function loadView() { }

  public function getCode(): string {

    // Load translations
    $this->loadLanguageModules();

    // Load metadata + head (title, scripts, includes, ...)
    if($this->loadView) {
      $this->loadView();
    }

    return '';
  }

  // UI Functions
  private function createList(array $items, string $tag, array $classes = []): string {

    $attributes = [];
    if (!empty($classes)) {
      $attributes["class"] = implode(" ", $classes);
    }

    $content = array_map(function ($item) { return html_tag("li", [], $item, false); }, $items);
    return html_tag_ex($tag, $attributes, $content, false);
  }

  public function createOrderedList(array $items=[], array $classes=[]): string {
    return $this->createList($items, "ol", $classes);
  }

  public function createUnorderedList(array $items=[], array $classes=[]): string {
    return $this->createList($items, "ul", $classes);
  }

  protected function createLink(string $link, $title=null, array $classes=[], bool $escapeTitle=true): string {
    $attrs = ["href" => $link];
    if (!empty($classes)) {
      $attrs["class"] = implode(" ", $classes);
    }

    return html_tag("a", $attrs, $title ?? $link, $escapeTitle);
  }

  protected function createExternalLink(string $link, $title=null, bool $escapeTitle=true): string {
    $attrs = ["href" => $link, "target" => "_blank", "rel" => "noopener noreferrer", "class" => "external"];
    return html_tag("a", $attrs, $title ?? $link, $escapeTitle);
  }

  protected function createIcon($icon, $type="fas", $classes = []): string {
    $classes = array_merge($classes, [$type, "fa-$icon"]);
    if ($icon === "spinner" || $icon === "circle-notch") {
      $classes[] = "fa-spin";
    }

    return html_tag("i", ["class" => implode(" ", $classes)]);
  }

  protected function createErrorText($text, $id="", $hidden=false): string {
    return $this->createStatusText("danger", $text, $id, $hidden);
  }

  protected function createWarningText($text, $id="", $hidden=false): string {
    return $this->createStatusText("warning", $text, $id, $hidden);
  }

  protected function createSuccessText($text, $id="", $hidden=false): string {
    return $this->createStatusText("success", $text, $id, $hidden);
  }

  protected function createSecondaryText($text, $id="", $hidden=false): string {
    return $this->createStatusText("secondary", $text, $id, $hidden);
  }

  protected function createInfoText($text, $id="", $hidden=false): string {
    return $this->createStatusText("info", $text, $id, $hidden);
  }

  protected function createStatusText(string $type, $text, string $id="", bool $hidden=false, array $classes=[]): string {
    $classes[] = "alert";
    $classes[] = "alert-$type";

    if ($hidden) {
      $classes[] = "hidden";
    }

    $attributes = [
      "class" => implode(" ", $classes),
      "role" => "alert"
    ];

    if (!empty($id)) {
      $attributes["id"] = $id;
    }

    return html_tag("div", $attributes, $text, false);
  }
}
