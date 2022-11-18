<?php

namespace Core\Elements;

abstract class Head extends View {

  protected array $sources;
  protected string $title;
  protected array $metas;
  protected array $rawFields;
  protected array $keywords;
  protected string $description;
  protected string $baseUrl;

  function __construct($document) {
    parent::__construct($document);
    $this->sources = array();
    $this->searchable = false;
    $this->metas = $this->initMetas();
    $this->rawFields = $this->initRawFields();
    $this->title = $this->initTitle();
    $this->initSources();
    $this->init();
  }

  protected abstract function initSources();
  protected abstract function initMetas(): array;
  protected abstract function initRawFields(): array;
  protected abstract function initTitle(): string;

  protected function init() {
    $this->keywords = array();
    $this->description = "";
    $this->baseUrl = "";
  }

  public function setBaseUrl($baseUrl) { $this->baseUrl = $baseUrl; }
  public function setDescription($description) { $this->description = $description; }
  public function setKeywords($keywords) { $this->keywords = $keywords; }
  public function setTitle($title) { $this->title = $title; }
  public function getSources(): array { return $this->sources; }
  public function addScript($type, $url, $js = '') { $this->sources[] = new Script($type, $url, $js); }
  public function addRawField($rawField) { $this->rawFields[] = $rawField; }
  public function addMeta($aMeta) { $this->metas[] = $aMeta; }
  public function addLink($rel, $href, $type = "") { $this->sources[] = new Link($rel, $href, $type); }
  public function addKeywords($keywords) { $this->keywords = array_merge($this->keywords, $keywords); }
  public function getTitle(): string { return $this->title; }

  public function addCSS($href, $type = Link::MIME_TEXT_CSS)  { $this->sources[] = new Link(Link::STYLESHEET, $href, $type); }
  public function addStyle($style) { $this->sources[] = new Style($style); }
  public function addJS($url) { $this->sources[] = new Script(Script::MIME_TEXT_JAVASCRIPT, $url, ""); }
  public function addJSCode($code) { $this->sources[] = new Script(Script::MIME_TEXT_JAVASCRIPT, "", $code); }

  public function loadFontawesome() {
    $this->addCSS(Link::FONTAWESOME);
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

  public function getCode(): string {
    $content = [];

    // meta tags
    foreach($this->metas as $meta) {
      $content[] = html_tag_short("meta", $meta);
    }

    // description
    if(!empty($this->description)) {
      $content[] = html_tag_short("meta", ["name" => "description", "content" => $this->description]);
    }

    // keywords
    if(!empty($this->keywords)) {
      $keywords = implode(", ", $this->keywords);
      $content[] = html_tag_short("meta", ["name" => "keywords", "content" => $keywords]);
    }

    // base tag
    if(!empty($this->baseUrl)) {
      $content[] = html_tag_short("base", ["href" => $this->baseUrl]);
    }

    // title
    $content[] = html_tag("title", [], $this->title);

    // src tags
    foreach($this->sources as $src) {
      $content[] = $src->getCode();
    }

    //
    foreach ($this->rawFields as $raw) {
      $content[] = $raw;
    }

    return html_tag("head", [], $content, false);
  }
}
