<?php

namespace Elements;

abstract class Head extends \View {

  protected $sources;
  protected $title;
  protected $metas;
  protected $rawFields;
  protected $keywords;
  protected $description;
  protected $baseUrl;

  function __construct($document) {
    parent::__construct($document);
    $this->sources = array();
    $this->metas = $this->initMetas();
    $this->rawFields = $this->initRawFields();
    $this->title = $this->initTitle();
    $this->initSources();
    $this->init();
  }

  protected abstract function initSources();
  protected abstract function initMetas();
  protected abstract function initRawFields();
  protected abstract function initTitle();

  protected function init() {
    $this->keywords = array();
    $this->description = "";
    $this->baseUrl = "";
  }

  public function setBaseUrl($baseUrl) { $this->baseUrl = $baseUrl; }
  public function setDescription($description) { $this->description = $description; }
  public function setKeywords($keywords) { $this->keywords = $keywords; }
  public function setTitle($title) { $this->title = $title; }
  public function getSources() { return $this->sources; }
  public function addScript($type, $url, $js = '') { $this->sources[] = new Script($type, $url, $js); }
  public function addRawField($rawField) { $this->rawFields[] = $rawField; }
  public function addMeta($aMeta) { $this->metas[] = $aMeta; }
  public function addLink($rel, $href, $type = "") { $this->sources[] = new Link($rel, $href, $type); }
  public function addKeywords($keywords) { array_merge($this->keywords, $keywords); }
  public function getTitle() { return $this->title; }

  public function addCSS($href, $type = Link::MIME_TEXT_CSS)  { $this->sources[] = new Link("stylesheet", $href, $type); }
  public function addStyle($style) { $this->sources[] = new Style($style); }
  public function addJS($url) { $this->sources[] = new Script(Script::MIME_TEXT_JAVASCRIPT, $url, ""); }
  public function addJSCode($code) { $this->sources[] = new Script(Script::MIME_TEXT_JAVASCRIPT, "", $code); }

  public function loadFontawesome() {
    $this->addCSS(Link::FONTAWESOME);
  }

  public function loadSyntaxHighlighting() {
    $this->addJS(Script::HIGHLIGHT);
    $this->addJSCode(Script::HIGHLIGHT_JS_LOADER);
    $this->addCSS(Link::HIGHLIGHT);
    $this->addCSS(Link::HIGHLIGHT_THEME);
  }

  public function loadJQueryTerminal($unixFormatting = true) {
    $this->addJS(Script::JQUERY_TERMINAL);
    if($unixFormatting) $this->addJS(Script::JQUERY_TERMINAL_UNIX);
    $this->addCSS(Link::JQUERY_TERMINAL);
  }

  public function loadGoogleRecaptcha($siteKey) {
    $this->addJS("https://www.google.com/recaptcha/api.js?render=$siteKey");
  }

  public function loadJQuery() {
    $this->addJS(Script::JQUERY);
  }

  public function loadBootstrap() {
    $this->addCSS(Link::BOOTSTRAP);
    $this->addJS(Script::BOOTSTRAP);
  }

  public function loadChartJS() {
    $this->addJS(Script::MOMENT);
    $this->addJS(Script::CHART);
  }

  public function getCode() {
    $header = "<head>";

    foreach($this->metas as $aMeta) {
      $header .= '<meta';
      foreach($aMeta as $key => $val) {
        $header .= " $key=\"$val\"";
      }
      $header .= ' />';
    }

    if(!empty($this->description)) {
      $header .= "<meta name=\"description\" content=\"$this->description\" />";
    }

    if(!empty($this->keywords)) {
      $keywords = implode(", ", $this->keywords);
      $header .= "<meta name=\"keywords\" content=\"$keywords\" />";
    }

    if(!empty($this->baseUrl)) {
      $header .= "<base href=\"$this->baseUrl\">";
    }

    $header .= "<title>$this->title</title>";

    foreach($this->sources as $src) {
      $header .= $src->getCode();
    }

    foreach($this->rawFields as $raw) {
      $header .= $raw;
    }

    $header .= "</head>";
    return $header;
  }
}
?>
