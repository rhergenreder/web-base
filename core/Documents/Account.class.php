<?php


namespace Documents;

use Elements\TemplateDocument;
use Objects\User;


class Account extends TemplateDocument {
  public function __construct(User $user, string $templateName) {
    parent::__construct($user, $templateName);
    $this->enableCSP();
  }

  private function createError(string $message) {
    $this->parameters["view"]["success"] = false;
    $this->parameters["view"]["message"] = $message;
  }

  protected function loadParameters() {
    $this->parameters["view"] = ["success" => true];
    if ($this->getTemplateName() === "account/reset_password.twig") {
      if (isset($_GET["token"]) && is_string($_GET["token"]) && !empty($_GET["token"])) {
        $this->parameters["view"]["token"] = $_GET["token"];
        $req = new \Api\User\CheckToken($this->getUser());
        $this->parameters["view"]["success"] = $req->execute(array("token" => $_GET["token"]));
        if ($this->parameters["view"]["success"]) {
          if (strcmp($req->getResult()["token"]["type"], "password_reset") !== 0) {
            $this->createError("The given token has a wrong type.");
          }
        } else {
          $this->createError("Error requesting password reset: " . $req->getLastError());
        }
      }
    } else if ($this->getTemplateName() === "account/register.twig") {
      $settings = $this->user->getConfiguration()->getSettings();
      if ($this->user->isLoggedIn()) {
        $this->createError("You are already logged in.");
      } else if (!$settings->isRegistrationAllowed()) {
        $this->createError("Registration is not enabled on this website.");
      }
    } else if ($this->getTemplateName() === "account/login.twig" && $this->user->isLoggedIn()) {
      header("Location: /admin");
      exit();
    } else if ($this->getTemplateName() === "account/accept_invite.twig") {
      if (isset($_GET["token"]) && is_string($_GET["token"]) && !empty($_GET["token"])) {
        $this->parameters["view"]["token"] = $_GET["token"];
        $req = new \Api\User\CheckToken($this->getUser());
        $this->parameters["view"]["success"] = $req->execute(array("token" => $_GET["token"]));
        if ($this->parameters["view"]["success"]) {
          if (strcmp($req->getResult()["token"]["type"], "invite") !== 0) {
            $this->createError("The given token has a wrong type.");
          } else {
            $this->parameters["view"]["invited_user"] = $req->getResult()["user"];
          }
        } else {
          $this->createError("Error confirming e-mail address: " . $req->getLastError());
        }
      } else {
        $this->createError("The link you visited is no longer valid");
      }
    }
  }
}