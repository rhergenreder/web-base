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
      $this->loadBootstrap();
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
      return "Welcome";
    }
  }

  class WelcomeBody extends SimpleBody {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function getContent() {
      return
        "<div class='container mt-5'>
          <div class='row'>
            <div class='col-lg-9 col-12 mx-auto'>
              <div class='jumbotron'>
                <h1>Congratulations!</h1>
                <p class='lead'>Your Web-Base Installation is now ready to use!</p>
                <hr class='my-4' />  
                <p>
                  You can now login into your <a href='/admin'>Administrator Dashboard</a> to adjust your settings
                  and add routes & pages.
                  You can add new documents and views by adding classes in the corresponding
                  directories and link to them, by creating rules in the Administrator Dashboard.
                </p>
              </div>
             </div>
          </div>
        </div>";
    }
  }
}