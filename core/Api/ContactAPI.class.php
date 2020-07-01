<?php

namespace Api {
  abstract class ContactAPI extends Request {

  }
}

namespace Api\Contact {

  use Api\ContactAPI;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\VerifyCaptcha;
  use Objects\User;

  class Request extends ContactAPI {

    private int $notificationId;
    private int $contactRequestId;

    public function __construct(User $user, bool $externalCall = false) {
      $parameters = array(
        'fromName' => new StringType('fromName', 32),
        'fromEmail' => new Parameter('fromEmail', Parameter::TYPE_EMAIL),
        'message' => new StringType('message', 512),
      );

      $settings = $user->getConfiguration()->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $parameters["captcha"] = new StringType("captcha");
      }

      parent::__construct($user, $externalCall, $parameters);
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $settings = $this->user->getConfiguration()->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $captcha = $this->getParam("captcha");
        $req = new VerifyCaptcha($this->user);
        if (!$req->execute(array("captcha" => $captcha, "action" => "contact"))) {
          return $this->createError($req->getLastError());
        }
      }

      if (!$this->insertContactRequest()) {
        return false;
      }

      $this->createNotification();

      if (!$this->success) {
        return $this->createError("The contact request was saved, but the server was unable to create a notification.");
      }

      return $this->success;
    }

    private function insertContactRequest() {
      $sql = $this->user->getSQL();
      $name = $this->getParam("fromName");
      $email = $this->getParam("fromEmail");
      $message = $this->getParam("message");

      $res = $sql->insert("ContactRequest", array("from_name", "from_email", "message"))
        ->addRow($name, $email, $message)
        ->returning("uid")
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->contactRequestId = $sql->getLastInsertId();
      }

      return $this->success;
    }

    private function createNotification() {
      $sql = $this->user->getSQL();
      $name = $this->getParam("fromName");
      $email = $this->getParam("fromEmail");
      $message = $this->getParam("message");

      $res = $sql->insert("Notification", array("title", "message", "type"))
        ->addRow("New Contact Request from: $name", "$name ($email) wrote:\n$message", "message")
        ->returning("uid")
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->notificationId = $sql->getLastInsertId();

        $res = $sql->insert("GroupNotification", array("group_id", "notification_id"))
          ->addRow(USER_GROUP_ADMIN, $this->notificationId)
          ->addRow(USER_GROUP_SUPPORT, $this->notificationId)
          ->execute();

        $this->success = ($res !== FALSE);
        $this->lastError = $sql->getLastError();
      }

      return $this->success;
    }
  }

}