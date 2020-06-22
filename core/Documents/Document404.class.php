<?php

namespace Documents {

  use Documents\Document404\Body404;
  use Documents\Document404\Head404;
  use Elements\Document;

  class Document404 extends Document {
    public function __construct($user, ?string $view = NULL) {
      parent::__construct($user, Head404::class, Body404::class, $view);
    }
  }
}

namespace Documents\Document404 {

  use Elements\Body;
  use Elements\Head;
  use Elements\SimpleBody;
  use Views\View404;

  class Head404 extends Head {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function initSources() {
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

  class Body404 extends SimpleBody {

    public function __construct($document) {
      parent::__construct($document);
    }

    public function loadView() {
      http_response_code(404);
    }

    protected function getContent() {
      return $this->load(View404::class);
    }
  }
}
