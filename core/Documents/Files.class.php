<?php

namespace Documents {

  use Documents\Files\FilesBody;
  use Documents\Files\FilesHead;
  use Elements\Document;
  use Objects\User;

  class Files extends Document {
    public function __construct(User $user, string $view = NULL) {
      parent::__construct($user, FilesHead::class, FilesBody::class, $view);
    }
  }
}

namespace Documents\Files {

  use Elements\Head;
  use Elements\Link;
  use Elements\Script;
  use Elements\SimpleBody;

  class FilesHead extends Head {

    protected function initSources() {
      $this->addCSS(Link::BOOTSTRAP);
      $this->loadFontawesome();
    }

    protected function initMetas(): array {
      return array(
        array('name' => 'viewport', 'content' => 'width=device-width, initial-scale=1.0'),
        array('name' => 'format-detection', 'content' => 'telephone=yes'),
        array('charset' => 'utf-8'),
        array('http-equiv' => 'expires', 'content' => '0'),
        array('name' => 'robots', 'content' => 'noarchive'),
        array('name' => 'referrer', 'content' => 'origin')
      );
    }

    protected function initRawFields(): array {
      return array();
    }

    protected function initTitle(): string {
      return "File Control Panel";
    }
  }

  class FilesBody extends SimpleBody {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function getContent(): string {
      $html = "<noscript>" . $this->createErrorText("Javascript is required for this site to render.") . "</noscript>";
      $html .= "<div id=\"root\"></div>";
      $html .= new Script(Script::MIME_TEXT_JAVASCRIPT, Script::FILES);
      return $html;
    }
  }

}
