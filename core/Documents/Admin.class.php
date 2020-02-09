<?php

namespace Documents {
  class Admin extends \Elements\Document {
    public function __construct($user) {
      parent::__construct($user, Admin\Head::class, Admin\Body::class);
      $this->databseRequired = false;
    }
  }
}

namespace Documents\Admin {

  class Head extends \Elements\Head {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function initSources() {
      $this->loadJQuery();
      $this->loadBootstrap();
      $this->loadFontawesome();
      $this->addJS(\Elements\Script::CORE);
      $this->addCSS(\Elements\Link::CORE);
      $this->addJS(\Elements\Script::ADMIN);
      $this->addCSS(\Elements\Link::ADMIN);
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
      return "WebBase - Administration";
    }
  }

  class Body extends \Elements\Body {

    public function __construct($document) {
      parent::__construct($document);
    }

    public function getCode() {
      $html = parent::getCode();

      $document = $this->getDocument();
      if(!$document->getUser()->isLoggedIn()) {
        $html .= new \Views\Login($document);
      } else {
        $html .= "You are logged in :]";
      }

      return $html;
    }
  }
}

?>
