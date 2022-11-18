<?php

namespace Core\Objects\lang;

abstract class LanguageModule {

  public abstract function getEntries(string $langCode);
}