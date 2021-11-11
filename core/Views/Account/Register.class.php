<?php


namespace Views\Account;

use Elements\Document;

class Register extends AccountView {

  public function __construct(Document $document, $loadView = true) {
    parent::__construct($document, $loadView);
    $this->title = "Registration";
    $this->description = "Create a new account";
    $this->icon = "user-plus";
  }

  public function getAccountContent() {

    $user = $this->getDocument()->getUser();
    if ($user->isLoggedIn()) {
      header(302);
      header("Location: /");
      die("You are already logged in.");
    }

    $settings = $user->getConfiguration()->getSettings();
    if (!$settings->isRegistrationAllowed()) {
      return $this->createErrorText(
        "Registration is not enabled on this website. If you are an administrator,
        goto <a href=\"/admin/settings\">/admin/settings</a>, to enable the user registration"
      );
    }

    return "<h4 class=\"pb-4\">Please fill with your details</h4>
      <form>
        <div class=\"input-group\">
          <div class=\"input-group-append\">
            <span class=\"input-group-text\"><i class=\"fas fa-hashtag\"></i></span>  
          </div>
          <input id=\"username\" autocomplete='username' name=\"username\" placeholder=\"Username\" class=\"form-control\" type=\"text\" maxlength=\"32\">
        </div>
        <div class=\"input-group mt-3\">
         <div class=\"input-group-append\">
            <span class=\"input-group-text\"><i class=\"fas fa-at\"></i></span>
          </div>
          <input type=\"email\" autocomplete='email' name='email' id='email' class=\"form-control\" placeholder=\"Email\" maxlength=\"64\">
        </div>
        <div class=\"input-group mt-3\">
          <div class=\"input-group-append\">
            <span class=\"input-group-text\"><i class=\"fas fa-key\"></i></span>
          </div>
          <input type=\"password\" autocomplete='new-password' name='password' id='password' class=\"form-control\" placeholder=\"Password\">
        </div>
        <div class=\"input-group mt-3\">
          <div class=\"input-group-append\">
            <span class=\"input-group-text\"><i class=\"fas fa-key\"></i></span>
          </div>
          <input type=\"password\" autocomplete='new-password' name='confirmPassword' id='confirmPassword' class=\"form-control\" placeholder=\"Confirm Password\">
        </div>
        <div class=\"input-group mt-3\">
          <button type=\"button\" class=\"btn btn-primary\" id='btnRegister'>Submit</button>          
          <a href='/login' style='margin-left: 10px'>
            <button class='btn btn-secondary' type='button'>
              Back to Login
            </button>
          </a>
        </div>
     </form>";
  }
}