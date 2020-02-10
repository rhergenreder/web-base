<?php

namespace Documents {
  class Document404 extends \Elements\Document {
    public function __construct($user) {
      parent::__construct($user, Document404\Head404::class, Document404\Body404::class);
    }
  }
}

namespace Documents\Document404 {

  class Head404 extends \Elements\Head {

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

  class Body404 extends \Elements\Body {

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

?>
