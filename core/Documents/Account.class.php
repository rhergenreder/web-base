<?php

namespace Documents {

  use Documents\Account\AccountBody;
  use Documents\Account\AccountHead;
  use Elements\Document;
  use Objects\User;

  class Account extends Document {
    public function __construct(User $user, ?string $view) {
      parent::__construct($user, AccountHead::class, AccountBody::class, $view);
    }
  }
}

namespace Documents\Account {

  use Elements\Head;
  use Elements\Script;
  use Elements\SimpleBody;

  class AccountHead extends Head {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function initSources() {
      $this->loadJQuery();
      $this->addJS(Script::CORE);
      $this->addJS(Script::ACCOUNT);
      $this->loadBootstrap();
      $this->loadFontawesome();
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
      return "Account";
    }
  }

  class AccountBody extends SimpleBody {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function getContent() {

      $view = $this->getDocument()->getView();
      if ($view === null) {
        return "The page you does not exist or is no longer valid. <a href='/'>Return to start page</a>";
      }

      return $view->getCode();
    }
  }
}