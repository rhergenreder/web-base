<?php


namespace Core\Documents;

use Core\Elements\TemplateDocument;
use Core\Objects\DatabaseEntity\UserToken;
use Core\Objects\Router\Router;


class Account extends TemplateDocument {
  public function __construct(Router $router, string $templateName) {
    parent::__construct($router, $templateName);
    $this->languageModules = ["general", "account"];
    $this->title = "Account";
    $this->searchable = false;
    $this->enableCSP();
  }

  private function createError(string $message) {
    $this->parameters["view"]["success"] = false;
    $this->parameters["view"]["message"] = $message;
  }

  protected function loadParameters() {
    $settings = $this->getSettings();
    $templateName = $this->getTemplateName();
    $language = $this->getContext()->getLanguage();
    $this->parameters["view"] = ["success" => true];
    switch ($templateName) {

      case "account/reset_password.twig": {
        if (isset($_GET["token"]) && is_string($_GET["token"]) && !empty($_GET["token"])) {
          $this->parameters["view"]["token"] = $_GET["token"];
          $req = new \Core\API\User\CheckToken($this->getContext());
          $this->parameters["view"]["success"] = $req->execute(array("token" => $_GET["token"]));
          if ($this->parameters["view"]["success"]) {
            if (strcmp($req->getToken()->getType(), UserToken::TYPE_PASSWORD_RESET) !== 0) {
              $this->createError("The given token has a wrong type.");
            }
          } else {
            $this->createError("Error requesting password reset: " . $req->getLastError());
          }
        }
        break;
      }

      case "account/register.twig": {
        if ($this->getUser()) {
          $this->createError("You are already logged in.");
        } else if (!$settings->isRegistrationAllowed()) {
          $this->createError("Registration is not enabled on this website.");
        }
        break;
      }

      case "account/login.twig": {
        if ($this->getUser()) {
          header("Location: /admin");
          exit();
        }
        break;
      }

      case "account/accept_invite.twig": {
        if (isset($_GET["token"]) && is_string($_GET["token"]) && !empty($_GET["token"])) {
          $this->parameters["view"]["token"] = $_GET["token"];
          $req = new \Core\API\User\CheckToken($this->getContext());
          $this->parameters["view"]["success"] = $req->execute(array("token" => $_GET["token"]));
          if ($this->parameters["view"]["success"]) {
            if (strcmp($req->getToken()->getType(), UserToken::TYPE_INVITE) !== 0) {
              $this->createError("The given token has a wrong type.");
            } else {
              $this->parameters["view"]["invited_user"] = $req->getToken()->getUser()->jsonSerialize();
            }
          } else {
            $this->createError("Error confirming e-mail address: " . $req->getLastError());
          }
        } else {
          $this->createError("The link you visited is no longer valid");
        }
        break;
      }

      default:
        break;
    }
  }
}