<?php

namespace Core\Elements;

use Core\Objects\Router\Router;

class HtmlDocument extends Document {

  protected ?Head $head;
  protected ?Body $body;
  private ?string $activeView;

  public function __construct(Router $router, $headClass, $bodyClass, ?string $view = NULL) {
    parent::__construct($router);
    $this->head = $headClass ? new $headClass($this) : null;
    $this->body = $bodyClass ? new $bodyClass($this) : null;
    $this->activeView = $view;
  }

  public function getHead(): ?Head { return $this->head; }
  public function getBody(): ?Body { return $this->body; }

  public function getView() : ?View {

    if ($this->activeView === null) {
      return null;
    }

    $view = parseClass($this->activeView);
    $file = getClassPath($view);
    if (!file_exists($file) || !is_subclass_of($view, View::class)) {
      return null;
    }

    return new $view($this);
  }

  public function getRequestedView(): string {
    return $this->activeView;
  }

  function getCode(array $params = []): string {
    // generate body first, so we can modify head
    $body = $this->body->getCode();

    if ($this->isCSPEnabled()) {
      foreach ($this->head->getSources() as $element) {
        if ($element instanceof Script || $element instanceof Link) {
          $element->setNonce($this->getCSPNonce());
        }
      }
    }

    $head = $this->head->getCode();
    $lang = $this->getContext()->getLanguage();

    $code = "<!DOCTYPE html>";
    $code .= html_tag("html", ["lang" => $lang->getShortCode()], $head . $body, false);

    return $code;
  }

  public function getTitle(): string {
    if ($this->head !== null) {
      return $this->head->getTitle();
    }

    return "Untitled Document";
  }
}