<?php

namespace Core\Elements;

abstract class TemplateView extends View {

  protected array $keywords = [];
  protected string $description = "";

  public function __construct(TemplateDocument $document) {
    parent::__construct($document);
    $this->title = "";
  }

  protected function getParameters(): array {
    return [];
  }

  public function loadParameters(array &$parameters): void {

    $this->loadView();

    $siteParameters = [
      "title" => $this->title,
      "description" => $this->description,
      "keywords" => $this->keywords
    ];

    foreach ($siteParameters as $key => $value) {
      if ($value) {
        $parameters["site"][$key] = $value;
      }
    }

    $parameters["view"] = $this->getParameters();
  }

  public function getCode(): string {
    return $this->getDocument()->getCode();
  }
}