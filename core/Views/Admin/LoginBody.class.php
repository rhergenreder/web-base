<?php

namespace Views\Admin;

use Elements\Body;
use Elements\Link;
use Elements\Script;
use Views\LanguageFlags;

class LoginBody extends Body {

  public function __construct($document) {
    parent::__construct($document);
  }

  public function loadView() {
    parent::loadView();
    $head = $this->getDocument()->getHead();
    $head->loadJQuery();
    $head->loadBootstrap();
    $head->addJS(Script::CORE);
    $head->addCSS(Link::CORE);
    $head->addJS(Script::ACCOUNT);
    $head->addCSS(Link::ACCOUNT);
  }

  public function getCode(): string {
    $html = parent::getCode();

    $username = L("Username");
    $password = L("Password");
    $login = L("Login");
    $backToStartPage = L("Back to Start Page");
    $stayLoggedIn = L("Stay logged in");

    $flags = $this->load(LanguageFlags::class);
    $iconBack = $this->createIcon("arrow-circle-left");
    $domain = $_SERVER['HTTP_HOST'];
    $protocol = getProtocol();

    $html .= "
      <body>
        <div class=\"container mt-4\">
          <div class=\"title text-center\">
            <h2>Admin Control Panel</h2>
          </div>
          <div class=\"row\">
             <div class=\"col-lg-6 col-12 m-auto\">
            <form class=\"loginForm\">
              <label for=\"username\">$username</label>
              <input type=\"text\" class=\"form-control\" name=\"username\" id=\"username\" placeholder=\"$username\" required autofocus />
              <label for=\"password\">$password</label>
              <input type=\"password\" class=\"form-control\" name=\"password\" id=\"password\" placeholder=\"$password\" required />
              <div class=\"form-check\">
                <input type=\"checkbox\" class=\"form-check-input\" id=\"stayLoggedIn\" name=\"stayLoggedIn\">
                <label class=\"form-check-label\" for=\"stayLoggedIn\">$stayLoggedIn</label>
              </div>
              <button class=\"btn btn-lg btn-primary btn-block\" id=\"btnLogin\" type=\"button\">$login</button>
              <div class=\"alert alert-danger\" style='display:none' role=\"alert\" id=\"alertMessage\"></div>
              <span class=\"flags position-absolute\">$flags</span>
            </form>
            <div class=\"p-1\">
              <a href=\"$protocol://$domain\">$iconBack&nbsp;$backToStartPage</a>
            </div>
          </div>
          </div>
        </div>
      </body>";

    return $html;
  }
}
