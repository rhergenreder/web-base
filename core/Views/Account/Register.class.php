<?php


namespace Views\Account;


use Elements\Document;
use Elements\View;

class Register extends AccountView {

  public function __construct(Document $document, $loadView = true) {
    parent::__construct($document, $loadView);
    $this->title = "Registration";
    $this->description = "Create a new account";
  }

  public function loadView() {
    parent::loadView();

    $document = $this->getDocument();
    $settings = $document->getUser()->getConfiguration()->getSettings();
    if ($settings->isRecaptchaEnabled()) {
      $document->getHead()->loadGoogleRecaptcha($settings->getRecaptchaSiteKey());
    }
  }

  public function getAccountContent() {

    $settings = $this->getDocument()->getUser()->getConfiguration()->getSettings();
    if (!$settings->isRegistrationAllowed()) {
      return $this->createErrorText(
        "Registration is not enabled on this website. If you are an administrator,
        goto <a href=\"/admin/settings\">/admin/settings</a>, to enable the user registration"
      );
    }

    return "<h4 class=\"pb-4\">Please fill with your details</h4>
      <form>
        <div class=\"form-group\">
          <input id=\"username\" name=\"username\" placeholder=\"Username\" class=\"form-control\" type=\"text\" maxlength=\"32\">
        </div>
        <div class=\"form-group\">
          <input type=\"email\" name='email' id='email' class=\"form-control\" placeholder=\"Email\" maxlength=\"64\">
        </div>
        <div class=\"form-group\">
          <input type=\"password\" name='password' id='password' class=\"form-control\" placeholder=\"Password\">
        </div>
        <div class=\"form-group\">
          <input type=\"password\" name='confirmPassword' id='confirmPassword' class=\"form-control\" placeholder=\"Confirm Password\">
        </div>
        <div class=\"form-group\">
          <button type=\"button\" class=\"btn btn-success\" id='btnRegister'>Submit</button>
        </div>
     </form>";
  }
}