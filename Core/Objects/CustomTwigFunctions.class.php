<?php

namespace Core\Objects;

use Core\Objects\DatabaseEntity\Language;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomTwigFunctions extends AbstractExtension {
  public function getFunctions(): array {
    return [
      new TwigFunction('L', array($this, 'translate')),
      new TwigFunction('LoadLanguageModule', array($this, 'loadLanguageModule')),
    ];
  }

  public function translate(string $key): string {
    return L($key);
  }

  public function loadLanguageModule(string $module): void {
    $language = Language::getInstance();
    $language->loadModule($module);
  }
}