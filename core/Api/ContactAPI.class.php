<?php

namespace Api {

  use Objects\User;

  abstract class ContactAPI extends Request {

    protected ?string $messageId;

    public function __construct(User $user, bool $externalCall, array $params) {
      parent::__construct($user, $externalCall, $params);
      $this->messageId = null;
      $this->csrfTokenRequired = false;

    }

    protected function sendMail(string $name, ?string $fromEmail, string $subject, string $message, ?string $to = null): bool {
      $request = new \Api\Mail\Send($this->user);
      $this->success = $request->execute(array(
        "subject" => $subject,
        "body" => $message,
        "replyTo" => $fromEmail,
        "replyName" => $name,
        "to" => $to
      ));

      $this->lastError = $request->getLastError();
      if ($this->success) {
        $this->messageId = $request->getResult()["messageId"];
      }

      return $this->success;
    }
  }
}

namespace Api\Contact {

  use Api\ContactAPI;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\VerifyCaptcha;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondNot;
  use Driver\SQL\Expression\CaseWhen;
  use Driver\SQL\Expression\Sum;
  use Objects\User;

  class Request extends ContactAPI {

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

    public function _execute(): bool {
      $settings = $this->user->getConfiguration()->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $captcha = $this->getParam("captcha");
        $req = new VerifyCaptcha($this->user);
        if (!$req->execute(array("captcha" => $captcha, "action" => "contact"))) {
          return $this->createError($req->getLastError());
        }
      }

      // parameter
      $message = $this->getParam("message");
      $name = $this->getParam("fromName");
      $email = $this->getParam("fromEmail");

      $sendMail = $this->sendMail($name, $email, "Contact Request", $message);
      $mailError = $this->getLastError();

      $insertDB = $this->insertContactRequest();
      $dbError = $this->getLastError();

      // Create a log entry
      if (!$sendMail || $mailError) {
        $message = "Error processing contact request.";
        if (!$sendMail) {
          $message .= " Mail: $mailError";
        }

        if (!$insertDB) {
          $message .= " Mail: $dbError";
        }
      }

      if (!$sendMail && !$insertDB) {
        return $this->createError("The contact request could not be sent. The Administrator is already informed. Please try again later.");
      }

      return $this->success;
    }

    private function insertContactRequest(): bool {
      $sql = $this->user->getSQL();
      $name = $this->getParam("fromName");
      $email = $this->getParam("fromEmail");
      $message = $this->getParam("message");
      $messageId = $this->messageId ?? null;

      $res = $sql->insert("ContactRequest", array("from_name", "from_email", "message", "messageId"))
        ->addRow($name, $email, $message, $messageId)
        ->returning("uid")
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class Respond extends ContactAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "requestId" => new Parameter("requestId", Parameter::TYPE_INT),
        'message' => new StringType('message', 512),
      ));
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    private function getSenderMail(): ?string {
      $requestId = $this->getParam("requestId");
      $sql = $this->user->getSQL();
      $res = $sql->select("from_email")
        ->from("ContactRequest")
        ->where(new Compare("uid", $requestId))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (empty($res)) {
          return $this->createError("Request does not exist");
        } else {
          return $res[0]["from_email"];
        }
      }

      return null;
    }

    private function insertResponseMessage(): bool {
      $sql = $this->user->getSQL();
      $message = $this->getParam("message");
      $requestId = $this->getParam("requestId");

      $this->success = $sql->insert("ContactMessage", ["request_id", "user_id", "message", "messageId", "read"])
        ->addRow($requestId, $this->user->getId(), $message, $this->messageId, true)
        ->execute();

      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    private function updateEntity() {
      $sql = $this->user->getSQL();
      $requestId = $this->getParam("requestId");

      $sql->update("EntityLog")
        ->set("modified", $sql->now())
        ->where(new Compare("entityId", $requestId))
        ->execute();
    }

    public function _execute(): bool {
      $message = $this->getParam("message");
      $senderMail = $this->getSenderMail();
      if (!$this->success) {
        return false;
      }

      $fromName = $this->user->getUsername();
      $fromEmail = $this->user->getEmail();

      if (!$this->sendMail($fromName, $fromEmail, "Re: Contact Request", $message, $senderMail)) {
        return false;
      }

      if (!$this->insertResponseMessage()) {
        return false;
      }

      $this->updateEntity();
      return $this->success;
    }
  }

  class Fetch extends ContactAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array());
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {
      $sql = $this->user->getSQL();
      $res = $sql->select("ContactRequest.uid", "from_name", "from_email", "from_name",
          new Sum(new CaseWhen(new CondNot("ContactMessage.read"), 1, 0), "unread"))
        ->from("ContactRequest")
        ->groupBy("ContactRequest.uid")
        ->leftJoin("ContactMessage", "ContactRequest.uid", "ContactMessage.request_id")
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["contactRequests"] = [];
        foreach ($res as $row) {
          $this->result["contactRequests"][] = array(
            "uid" => intval($row["uid"]),
            "from_name" => $row["from_name"],
            "from_email" => $row["from_email"],
            "unread" => intval($row["unread"]),
          );
        }
      }

      return $this->success;
    }
  }

  class Get extends ContactAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "requestId" => new Parameter("requestId", Parameter::TYPE_INT),
      ));
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    private function updateRead() {
      $requestId = $this->getParam("requestId");
      $sql = $this->user->getSQL();
      $sql->update("ContactMessage")
        ->set("read", 1)
        ->where(new Compare("request_id", $requestId))
        ->execute();
    }

    public function _execute(): bool {
      $requestId = $this->getParam("requestId");
      $sql = $this->user->getSQL();

      $res = $sql->select("from_name", "from_email", "message", "created_at")
        ->from("ContactRequest")
        ->where(new Compare("uid", $requestId))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (empty($res)) {
          return $this->createError("Request does not exist");
        } else {
          $row = $res[0];
          $this->result["request"] = array(
            "from_name" => $row["from_name"],
            "from_email" => $row["from_email"],
            "messages" => array(
              ["sender_id" => null, "message" => $row["message"], "timestamp" => $row["created_at"]]
            )
          );

          $res = $sql->select("user_id", "message", "created_at")
            ->from("ContactMessage")
            ->where(new Compare("request_id", $requestId))
            ->orderBy("created_at")
            ->execute();

          $this->success = ($res !== false);
          $this->lastError = $sql->getLastError();

          if ($this->success) {
            foreach ($res as $row) {
              $this->result["request"]["messages"][] = array(
                "sender_id" => $row["user_id"], "message" => $row["message"], "timestamp" => $row["created_at"]
              );
            }

            $this->updateRead();
          }
        }
      }

      return $this->success;
    }
  }
}