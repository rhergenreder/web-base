<?php

namespace Documents {

  use Documents\Admin\AdminHead;
  use Elements\Document;
  use Objects\User;
  use Views\AdminDashboard;
  use Views\LoginBody;

  class Admin extends Document {
    public function __construct(User $user) {
      $body = $user->isLoggedIn() ? AdminDashboard::class : LoginBody::class;
      parent::__construct($user, AdminHead::class, $body);
    }
  }
}

namespace Documents\Admin {

  use Elements\Head;
  use Elements\Link;
  use Elements\Script;

  class AdminHead extends Head {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function initSources() {
      $this->loadJQuery();
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
}