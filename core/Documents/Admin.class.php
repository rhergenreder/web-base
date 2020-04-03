<?php

namespace Documents {

  use Documents\Admin\AdminBody;
  use Documents\Admin\AdminHead;
  use Elements\Document;

  class Admin extends Document {
    public function __construct($user) {
      parent::__construct($user, AdminHead::class, AdminBody::class);
    }
  }
}

namespace Documents\Admin {

  use Elements\Body;
  use Elements\Head;
  use Elements\Link;
  use Elements\Script;
  use Views\Admin;
  use Views\Login;

  class AdminHead extends Head {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function initSources() {
      $this->loadJQuery();
      $this->loadBootstrap();
      $this->loadFontawesome();
      $this->addJS(Script::CORE);
      $this->addCSS(Link::CORE);
      $this->addJS(Script::ADMIN);
      $this->addCSS(Link::ADMIN);
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

  class AdminBody extends Body {

    public function __construct($document) {
      parent::__construct($document);
    }

    public function getCode() {
      $html = parent::getCode();

      $document = $this->getDocument();
      if(!$document->getUser()->isLoggedIn()) {
        $html .= new Login($document);
      } else {
        $html .= new Admin($document);
      }

      return $html;
    }
  }
}