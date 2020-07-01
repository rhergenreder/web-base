<?php

namespace Objects\lang;

abstract class LanguageModule {

  public abstract function getEntries(string $langCode);
}