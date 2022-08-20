<?php

namespace Objects\Search;

class SearchQuery {

  private string $query;
  private array $parts;

  public function __construct(string $query) {
    $this->query = $query;
    $this->parts = array_unique(array_filter(explode(" ", strtolower($query))));
  }

  public function getQuery(): string {
    return $this->query;
  }

}