<?php

namespace Core\Objects\Search;

use Core\Objects\ApiObject;

class SearchResult extends ApiObject {

  private string $url;
  private string $title;
  private string $text;

  public function __construct(string $url, string $title, string $text) {
    $this->url = $url;
    $this->title = $title;
    $this->text =  $text;
  }

  public function jsonSerialize(): array {
    return [
      "url" => $this->url,
      "title" => $this->title,
      "text" => $this->text
    ];
  }

  public function setUrl(string $url) {
    $this->url = $url;
  }
}