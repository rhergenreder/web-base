<?php

namespace Core\API {

  use Core\Objects\Context;

  abstract class ContactAPI extends Request {

    protected ?string $messageId;

    public function __construct(Context $context, bool $externalCall, array $params) {
      parent::__construct($context, $externalCall, $params);
      $this->messageId = null;
      $this->csrfTokenRequired = false;
    }

    protected function sendMail(string $name, ?string $fromEmail, string $subject, string $message, ?string $to = null): bool {
      $request = new \Core\API\Mail\Send($this->context);
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

namespace Core\API\Contact {

  use Core\API\ContactAPI;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\VerifyCaptcha;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondNot;
  use Core\Driver\SQL\Expression\CaseWhen;
  use Core\Driver\SQL\Expression\Sum;
  use Core\Objects\Context;

  class Request extends ContactAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      $parameters = array(
        'fromName' => new StringType('fromName', 32),
        'fromEmail' => new Parameter('fromEmail', Parameter::TYPE_EMAIL),
        'message' => new StringType('message', 512),
      );

      $settings = $context->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $parameters["captcha"] = new StringType("captcha");
      }

      parent::__construct($context, $externalCall, $parameters);
    }

    public function _execute(): bool {
      $settings = $this->context->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $captcha = $this->getParam("captcha");
        $req = new VerifyCaptcha($this->context);
        if (!$req->execute(array("captcha" => $captcha, "action" => "contact"))) {
          return $this->createError($req->getLastError());
        }
      }

      // parameter
      $message = $this->getParam("message");
      $name = $this->getParam("fromName");
      $email = $this->getParam("fromEmail");

      $sendMail = $this->sendMail($name, $email, "Contact Request", $message);
      $insertDB = $this->insertContactRequest();
      if (!$sendMail && !$insertDB) {
        return $this->createError("The contact request could not be sent. The Administrator is already informed. Please try again later.");
      }

      return $this->success;
    }

    private function insertContactRequest(): bool {
      $sql = $this->context->getSQL();
      $name = $this->getParam("fromName");
      $email = $this->getParam("fromEmail");
      $message = $this->getParam("message");
      $messageId = $this->messageId ?? null;

      $res = $sql->insert("ContactRequest", array("from_name", "from_email", "message", "messageId"))
        ->addRow($name, $email, $message, $messageId)
        ->returning("id")
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class Respond extends ContactAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "requestId" => new Parameter("requestId", Parameter::TYPE_INT),
        'message' => new StringType('message', 512),
      ));
      $this->loginRequired = true;
    }

    private function getSenderMail(): ?string {
      $requestId = $this->getParam("requestId");
      $sql = $this->context->getSQL();
      $res = $sql->select("from_email")
        ->from("ContactRequest")
        ->where(new Compare("id", $requestId))
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
      $sql = $this->context->getSQL();
      $message = $this->getParam("message");
      $requestId = $this->getParam("requestId");

      $this->success = $sql->insert("ContactMessage", ["request_id", "user_id", "message", "messageId", "read"])
        ->addRow($requestId, $this->context->getUser()->getId(), $message, $this->messageId, true)
        ->execute();

      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    private function updateEntity() {
      $sql = $this->context->getSQL();
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

      $user = $this->context->getUser();
      $fromName = $user->getUsername();
      $fromEmail = $user->getEmail();

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

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array());
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();
      $res = $sql->select("ContactRequest.id", "from_name", "from_email", "from_name",
          new Sum(new CaseWhen(new CondNot("ContactMessage.read"), 1, 0), "unread"))
        ->from("ContactRequest")
        ->groupBy("ContactRequest.id")
        ->leftJoin("ContactMessage", "ContactRequest.id", "ContactMessage.request_id")
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["contactRequests"] = [];
        foreach ($res as $row) {
          $this->result["contactRequests"][] = array(
            "id" => intval($row["id"]),
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

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "requestId" => new Parameter("requestId", Parameter::TYPE_INT),
      ));
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    private function updateRead() {
      $requestId = $this->getParam("requestId");
      $sql = $this->context->getSQL();
      $sql->update("ContactMessage")
        ->set("read", 1)
        ->where(new Compare("request_id", $requestId))
        ->execute();
    }

    public function _execute(): bool {
      $requestId = $this->getParam("requestId");
      $sql = $this->context->getSQL();

      $res = $sql->select("from_name", "from_email", "message", "created_at")
        ->from("ContactRequest")
        ->where(new Compare("id", $requestId))
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