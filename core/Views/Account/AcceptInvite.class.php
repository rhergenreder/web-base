<?php


namespace Views\Account;


use Elements\Document;
use Elements\View;

class AcceptInvite extends AccountView {

  private bool $success;
  private string $message;
  private array $invitedUser;

  public function __construct(Document $document, $loadView = true) {
    parent::__construct($document, $loadView);
    $this->title = "Invitation";
    $this->description = "Finnish your account registration by choosing a password.";
    $this->icon = "user-check";
    $this->success = false;
    $this->message = "No content";
    $this->invitedUser = array();
  }

  public function loadView() {
    parent::loadView();

    if (isset($_GET["token"]) && is_string($_GET["token"]) && !empty($_GET["token"])) {
      $req = new \Api\User\CheckToken($this->getDocument()->getUser());
      $this->success = $req->execute(array("token" => $_GET["token"]));
      if ($this->success) {
        if (strcmp($req->getResult()["token"]["type"], "invite") !== 0) {
          $this->success = false;
          $this->message = "The given token has a wrong type.";
        } else {
          $this->invitedUser = $req->getResult()["user"];
        }
      } else {
        $this->message = "Error confirming e-mail address: " . $req->getLastError();
      }
    } else {
      $this->success = false;
      $this->message = "The link you visited is no longer valid";
    }
  }

  protected function getAccountContent() {
    if (!$this->success) {
      return $this->createErrorText($this->message);
    }

    $token = htmlspecialchars($_GET["token"], ENT_QUOTES);
    $username = $this->invitedUser["name"];
    $emailAddress = $this->invitedUser["email"];

    return "<h4 class=\"pb-4\">Please fill with your details</h4>
      <form>
        <input name='token' id='token' type='hidden' value='$token'/>
        <div class=\"input-group\">
          <div class=\"input-group-append\">
            <span class=\"input-group-text\"><i class=\"fas fa-hashtag\"></i></span>  
          </div>
          <input id=\"username\" name=\"username\" placeholder=\"Username\" class=\"form-control\" type=\"text\" maxlength=\"32\" value='$username' disabled>
        </div>
        <div class=\"input-group mt-3\">
         <div class=\"input-group-append\">
            <span class=\"input-group-text\"><i class=\"fas fa-at\"></i></span>
          </div>
          <input type=\"email\" name='email' id='email' class=\"form-control\" placeholder=\"Email\" maxlength=\"64\" value='$emailAddress' disabled>
        </div>
        <div class=\"input-group mt-3\">
          <div class=\"input-group-append\">
            <span class=\"input-group-text\"><i class=\"fas fa-key\"></i></span>
          </div>
          <input type=\"password\" name='password' id='password' class=\"form-control\" placeholder=\"Password\">
        </div>
        <div class=\"input-group mt-3\">
          <div class=\"input-group-append\">
            <span class=\"input-group-text\"><i class=\"fas fa-key\"></i></span>
          </div>
          <input type=\"password\" name='confirmPassword' id='confirmPassword' class=\"form-control\" placeholder=\"Confirm Password\">
        </div>
        <div class=\"input-group mt-3\">
          <button type=\"button\" class=\"btn btn-success\" id='btnAcceptInvite'>Submit</button>
        </div>
     </form>";
  }
}