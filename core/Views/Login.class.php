<?php

namespace Views;

class Login extends \View {
  public function __construct($document) {
    parent::__construct($document);
  }

  public function getCode() {
    $html = parent::getCode();

    $username = L("Username");
    $password = L("Password");
    $rememberMe = L("Remember me");
    $login = L("Login");
    $backToStartPage = L("Back to Start Page");
    $flags = new LanguageFlags($this->getDocument());
    $iconBack = $this->createIcon("arrow-circle-left", "right");
    $domain = $_SERVER['HTTP_HOST'];
    $protocol = getProtocol();

    $accountCreated = "";
    if(isset($_GET["accountCreated"])) {
      $accountCreated .= '
        <div class="alert alert-success margin-top-xs" id="accountCreated">
          Your account was successfully created, you may now login with your credentials
        </div>';
    }

    $html = "
      <div class=\"container margin-top-xxl\">
        <div class=\"title text-center\">
          <h2>Admin Control Panel</h2>
        </div>
        <div class=\"loginContainer margin-center\">
          <form class=\"loginForm\">
            <label for=\"username\">$username</label>
            <input type=\"text\" class=\"form-control\" name=\"username\" id=\"username\" placeholder=\"$username\" required autofocus />
            <label for=\"password\">$password</label>
            <input type=\"password\" class=\"form-control\" name=\"password\" id=\"password\" placeholder=\"$password\" required />
            <button class=\"btn btn-lg btn-primary btn-block\" id=\"btnLogin\" type=\"button\">$login</button>
            <div class=\"alert alert-danger hidden\" role=\"alert\" id=\"loginError\"></div>
          </form>
          <span class=\"subtitle flags-container\"><span class=\"flags\">$flags</span></span>
          <span class=\"subtitle\"><a class=\"link\" href=\"$protocol://$domain\">$iconBack&nbsp;$backToStartPage</a></span>
          $accountCreated
        </div>
      </div>";

    return $html;
  }
}

?>
