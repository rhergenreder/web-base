<?php

namespace Views;

use Elements\Body;
use Elements\Link;
use Elements\Script;

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
    $head->addJS(Script::ADMIN);
    $head->addCSS(Link::ADMIN);
  }

  public function getCode() {
    $html = parent::getCode();

    $username = L("Username");
    $password = L("Password");
    $login = L("Login");
    $backToStartPage = L("Back to Start Page");
    $stayLoggedIn = L("Stay logged in");

    $flags = new LanguageFlags($this->getDocument());
    $iconBack = $this->createIcon("arrow-circle-left");
    $domain = $_SERVER['HTTP_HOST'];
    $protocol = getProtocol();

    $html .= "<body>";

    $accountCreated = "";
    if(isset($_GET["accountCreated"])) {
      $accountCreated =
        '<div class="alert alert-success mt-3" id="accountCreated">
          Your account was successfully created, you may now login with your credentials
        </div>';
    }

    $html .= "
      <div class=\"container mt-4\">
        <div class=\"title text-center\">
          <h2>Admin Control Panel</h2>
        </div>
        <div class=\"loginContainer m-auto\">
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
            <div class=\"alert alert-danger hidden\" role=\"alert\" id=\"loginError\"></div>
            <span class=\"flags position-absolute\">$flags</span>
          </form>
          <div class=\"p-1\">
            <a href=\"$protocol://$domain\">$iconBack&nbsp;$backToStartPage</a>
          </div>
          $accountCreated
        </div>
      </div>
     </body>";

    return $html;
  }
}
