<?php


namespace Views\Account;


use Elements\Document;

class ResetPassword extends AccountView {

  private bool $success;
  private string $message;
  private ?string $token;

  public function __construct(Document $document, $loadView = true) {
    parent::__construct($document, $loadView);
    $this->title = "Reset Password";
    $this->description = "Request a password reset, once you got the e-mail address, you can choose a new password";
    $this->icon = "user-lock";
    $this->success = true;
    $this->message = "";
    $this->token = NULL;
  }

  public function loadView() {
    parent::loadView();

    if (isset($_GET["token"]) && is_string($_GET["token"]) && !empty($_GET["token"])) {
      $this->token = $_GET["token"];
      $req = new \Api\User\CheckToken($this->getDocument()->getUser());
      $this->success = $req->execute(array("token" => $_GET["token"]));
      if ($this->success) {
        if (strcmp($req->getResult()["token"]["type"], "password_reset") !== 0) {
          $this->success = false;
          $this->message = "The given token has a wrong type.";
        }
      } else {
        $this->message = "Error requesting password reset: " . $req->getLastError();
      }
    }
  }

  protected function getAccountContent() {
    if (!$this->success) {
      $html = $this->createErrorText($this->message);
      if ($this->token !== null) {
        $html .= "<a href='/resetPassword' class='btn btn-primary'>Go back</a>";
      }
      return $html;
    }

    if ($this->token === null) {
      return  "<p class='lead'>Enter your E-Mail address, to receive a password reset token.</p>
          <form>
        <div class=\"input-group\">
          <div class=\"input-group-append\">
            <span class=\"input-group-text\"><i class=\"fas fa-at\"></i></span>  
          </div>
          <input id=\"email\" autocomplete='email' name=\"email\" placeholder=\"E-Mail address\" class=\"form-control\" type=\"email\" maxlength=\"64\" />
        </div>
        <div class=\"input-group mt-2\" style='position: absolute;bottom: 15px'>
          <button id='btnRequestPasswordReset' class='btn btn-primary'>
            Request
          </button>
          <a href='/login' style='margin-left: 10px'>
            <button class='btn btn-secondary' type='button'>
              Back to Login
            </button>
          </a>
        </div>
      ";
    } else {
      return "<h4 class=\"pb-4\">Choose a new password</h4>
      <form>
        <input name='token' id='token' type='hidden' value='$this->token'/>
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
          <button type=\"button\" class=\"btn btn-primary\" id='btnResetPassword'>Submit</button>
          <a href='/login' style='margin-left: 10px; display: none' id='backToLogin'>
            <button class='btn btn-success' type='button'>
              Back to Login
            </button>
          </a>
        </div>
     </form>";
    }
  }
}