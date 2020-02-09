<?php

namespace Elements;

class Source extends \View {

  protected $sourceType;
  protected $url;

  public function __construct($sourceType, $url) {
    $this->sourceType = $sourceType;
    $this->url = $url;
  }

  public function getCode() {
    return "<$sourceType />";
  }

  public function getUrl() { return $this->url; }
}

?>
