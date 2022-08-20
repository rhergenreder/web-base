<?php

namespace Objects;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomTwigFunctions extends AbstractExtension {
  public function getFunctions(): array {
    return [
      new TwigFunction('L', array($this, 'translate')),
    ];
  }

  public function translate(string $key): string {
    return L($key);
  }
}