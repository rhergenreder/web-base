<?php

namespace Api;

use Api\Parameter\StringType;
use Objects\Context;
use Objects\Search\Searchable;
use Objects\Search\SearchQuery;

class Search extends Request {

  public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
    parent::__construct($context, $externalCall, [
      "text" => new StringType("text", 32)
    ]);
  }

  protected function _execute(): bool {

    $query = new SearchQuery(trim($this->getParam("text")));
    if (strlen($query->getQuery()) < 3) {
      return $this->createError("You have to type at least 3 characters to search for");
    }

    $router = $this->context->router;
    if ($router === null) {
      return $this->createError("There is currently no router configured. Error during installation?");
    }

    $this->result["results"] = [];
    foreach ($router->getRoutes(false) as $route) {
      if(in_array(Searchable::class, array_keys((new \ReflectionClass($route))->getTraits()))) {
        foreach ($route->doSearch($this->context, $query) as $searchResult) {
          $this->result["results"][] = $searchResult->jsonSerialize();
        }
      }
    }

    return true;
  }
}