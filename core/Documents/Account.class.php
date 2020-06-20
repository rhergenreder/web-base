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
  use Elements\SimpleBody;

  class AccountHead extends Head {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function initSources() {

    }

    protected function initMetas() {
      return array();
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