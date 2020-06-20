<?php

namespace Documents {

  use Documents\Welcome\WelcomeBody;
  use Documents\Welcome\WelcomeHead;
  use Elements\Document;
  use Objects\User;

  class Welcome extends Document {
    public function __construct(User $user, ?string $view) {
      parent::__construct($user, WelcomeHead::class, WelcomeBody::class, $view);
    }
  }
}

namespace Documents\Welcome {

  use Elements\Head;
  use Elements\SimpleBody;

  class WelcomeHead extends Head {

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
      return "Welcome";
    }
  }

  class WelcomeBody extends SimpleBody {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function getContent() {
      return "Welcome!";
    }
  }
}