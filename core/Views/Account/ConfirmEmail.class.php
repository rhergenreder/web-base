<?php


namespace Views\Account;


use Elements\Document;

class ConfirmEmail extends AccountView {

  private bool $success;
  private string $message;

  public function __construct(Document $document, $loadView = true) {
    parent::__construct($document, $loadView);
    $this->title = "Confirm Email";
    $this->icon = "user-check";
    $this->success = false;
    $this->message = "No content";
  }

  public function loadView() {
    parent::loadView();

    if (isset($_GET["token"]) && is_string($_GET["token"]) && !empty($_GET["token"])) {
      $req = new \Api\User\ConfirmEmail($this->getDocument()->getUser());
      $this->success = $req->execute(array("token" => $_GET["token"]));
      if ($this->success) {
        $this->message = "Your e-mail address was successfully confirmed, you may now log in";
      } else {
        $this->message = "Error confirming e-mail address: " . $req->getLastError();
      }
    } else {
      $this->success = false;
      $this->message = "The link you visited is no longer valid";
    }
  }

  protected function getAccountContent() {
    if ($this->success) {
      return $this->createSuccessText($this->message);
    } else {
      return $this->createErrorText($this->message);
    }
  }
}