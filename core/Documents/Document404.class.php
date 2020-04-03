<?php

namespace Documents {

  use Documents\Document404\Body404;
  use Documents\Document404\Head404;
  use Elements\Document;

  class Document404 extends Document {
    public function __construct($user) {
      parent::__construct($user, Head404::class, Body404::class);
    }
  }
}

namespace Documents\Document404 {

  use Elements\Body;
  use Elements\Head;

  class Head404 extends Head {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function initSources() {
      // $this->loadJQuery();
      // $this->loadBootstrap();
      // $this->loadFontawesome();
      // $this->addJS(\Elements\Script::CORE);
      // $this->addCSS(\Elements\Link::CORE);
    }

    protected function initMetas() {
      return array(
        array('name' => 'viewport', 'content' => 'width=device-width, initial-scale=1.0'),
        array('name' => 'format-detection', 'content' => 'telephone=yes'),
        array('charset' => 'utf-8'),
        array("http-equiv" => 'expires', 'content' => '0'),
        array("name" => 'robots', 'content' => 'noarchive'),
      );
    }

    protected function initRawFields() {
      return array();
    }

    protected function initTitle() {
      return "WebBase - Not Found";
    }
  }

  class Body404 extends Body {

    public function __construct($document) {
      parent::__construct($document);
    }

    public function getCode() {
      $html = parent::getCode();
      $html .= "<b>404 Not Found</b>";
      return $html;
    }
  }
}
