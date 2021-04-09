<?php

namespace Api {

  use Objects\ConnectionData;

  abstract class MailAPI extends Request {
    protected function getMailConfig(): ?ConnectionData {
      $req = new \Api\Settings\Get($this->user);
      $this->success = $req->execute(array("key" => "^mail_"));
      $this->lastError = $req->getLastError();

      if ($this->success) {
        $settings = $req->getResult()["settings"];

        if (!isset($settings["mail_enabled"]) || $settings["mail_enabled"] !== "1") {
          $this->createError("Mail is not configured yet.");
          return null;
        }

        $host = $settings["mail_host"] ?? "localhost";
        $port = intval($settings["mail_port"] ?? "25");
        $login = $settings["mail_username"] ?? "";
        $password = $settings["mail_password"] ?? "";
        $connectionData = new ConnectionData($host, $port, $login, $password);
        $connectionData->setProperty("from", $settings["mail_from"] ?? "");
        $connectionData->setProperty("last_sync", $settings["mail_last_sync"] ?? "");
        return $connectionData;
      }

      return null;
    }
  }
}

namespace Api\Mail {

  use Api\MailAPI;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Driver\SQL\Column\Column;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Strategy\UpdateStrategy;
  use External\PHPMailer\Exception;
  use External\PHPMailer\PHPMailer;
  use Objects\ConnectionData;
  use Objects\User;

  class Test extends MailAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "receiver" => new Parameter("receiver", Parameter::TYPE_EMAIL)
      ));
    }

    public function execute($values = array()): bool {
      if (!parent::execute($values)) {
        return false;
      }

      $receiver = $this->getParam("receiver");
      $req = new \Api\Mail\Send($this->user);
      $this->success = $req->execute(array(
        "to" => $receiver,
        "subject" => "Test E-Mail",
        "body" => "Hey! If you receive this e-mail, your mail configuration seems to be working."
      ));

      $this->lastError = $req->getLastError();
      return $this->success;
    }
  }

  class Send extends MailAPI {
    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'to' => new Parameter('to', Parameter::TYPE_EMAIL, true, null),
        'subject' => new StringType('subject', -1),
        'body' => new StringType('body', -1),
        'replyTo' => new Parameter('replyTo', Parameter::TYPE_EMAIL, true, null),
        'replyName' => new StringType('replyName', 32, true, "")
      ));
      $this->isPublic = false;
    }

    public function execute($values = array()): bool {
      if (!parent::execute($values)) {
        return false;
      }

      $mailConfig = $this->getMailConfig();
      if (!$this->success) {
        return false;
      }

      $fromMail = $mailConfig->getProperty('from');
      $toMail = $this->getParam('to') ?? $fromMail;
      $subject = $this->getParam('subject');
      $replyTo = $this->getParam('replyTo');
      $replyName = $this->getParam('replyName');

      try {
        $mail = new PHPMailer;
        $mail->IsSMTP();
        $mail->setFrom($fromMail);
        $mail->addAddress($toMail);

        if ($replyTo) {
          $mail->addReplyTo($replyTo, $replyName);
        }

        $mail->Subject = $subject;
        $mail->SMTPDebug = 0;
        $mail->Host = $mailConfig->getHost();
        $mail->Port = $mailConfig->getPort();
        $mail->SMTPAuth = true;
        $mail->Username = $mailConfig->getLogin();
        $mail->Password = $mailConfig->getPassword();
        $mail->SMTPSecure = 'tls';
        $mail->IsHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Body = $this->getParam('body');

        $this->success = @$mail->Send();
        if (!$this->success) {
          $this->lastError = "Error sending Mail: $mail->ErrorInfo";
          error_log("sendMail() failed: $mail->ErrorInfo");
        } else {
          $this->result["messageId"] = $mail->getLastMessageID();
        }
      } catch (Exception $e) {
        $this->success = false;
        $this->lastError = "Error sending Mail: $e";
      }

      return $this->success;
    }
  }

  // TODO: IMAP mail settings :(
  class Sync extends MailAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array());
      $this->csrfTokenRequired = true;
    }

    private function fetchMessageIds() {
      $sql = $this->user->getSQL();
      $res = $sql->select("uid", "messageId")
        ->from("ContactRequest")
        ->where(new Compare("messageId", NULL, "!="))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      }

      $messageIds = [];
      foreach ($res as $row) {
        $messageIds[$row["messageId"]] = $row["uid"];
      }
      return $messageIds;
    }

    private function findContactRequest(array &$messageIds, array &$references): ?int {
      foreach ($references as &$ref) {
        if (isset($messageIds[$ref])) {
          return $messageIds[$ref];
        }
      }

      return null;
    }

    private function parseBody(string $body): string {
      // TODO clean this up
      return trim($body);
    }

    private function insertMessages($messages): bool {
      $sql = $this->user->getSQL();

      $query = $sql->insert("ContactMessage", ["request_id", "user_id", "message", "messageId"])
        ->onDuplicateKeyStrategy(new UpdateStrategy(["message_id"], ["message" => new Column("message")]));

      foreach ($messages as $message) {
        $query->addRow(
          $message["requestId"],
          $sql->select("uid")->from("User")->where(new Compare("email", $message["from"]))->limit(1),
          $message["body"],
          $message["messageId"]
        );
      }

      $this->success = $query->execute();
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    private function parseDate($date) {
      $formats = [null, "D M d Y H:i:s e+", "D, j M Y H:i:s e+"];
      foreach ($formats as $format) {
        try {
          $dateObj = ($format === null ? new \DateTime($date) : \DateTime::createFromFormat($format, $date));
          if ($dateObj) {
            return $dateObj;
          }
        } catch (\Exception $exception) {
        }
      }

      return $this->createError("Could not parse date: $date");
    }

    private function getReference(ConnectionData $mailConfig): string {
      $port = 993;
      $host = str_replace("smtp", "imap", $mailConfig->getHost());
      $flags = ["/ssl"];
      return '{' . $host . ':' . $port . implode("", $flags) . '}';
    }

    private function connect(ConnectionData $mailConfig) {

      $username = $mailConfig->getLogin();
      $password = $mailConfig->getPassword();
      $ref = $this->getReference($mailConfig);
      $mbox = @imap_open($ref, $username, $password, OP_READONLY);
      if (!$mbox) {
        return $this->createError("Can't connect to mail server via IMAP: " . imap_last_error());
      }

      return $mbox;
    }

    private function listFolders(ConnectionData $mailConfig, $mbox) {

      $boxes = @imap_list($mbox, $this->getReference($mailConfig), '*');
      if (!$boxes) {
        return $this->createError("Error listing imap folders: " . imap_last_error());
      }

      return $boxes;
    }

    private function runSearch($mbox, string $searchCriteria, ?\DateTime $lastSyncDateTime, &$messages) {
      $result = @imap_search($mbox, $searchCriteria);
      if ($result === false) {
        return $this->createError("Could not run search: " . imap_last_error());
      }

      foreach ($result as $msgNo) {
        $header = imap_headerinfo($mbox, $msgNo);
        $date   = $this->parseDate($header->date);
        if ($date === false) {
          return false;
        }

        if ($lastSyncDateTime === null || \datetimeDiff($lastSyncDateTime, $date) > 0) {

          $references = property_exists($header, "references") ?
            explode(" ", $header->references) : [];

          $requestId = $this->findContactRequest($messageIds, $references);
          if ($requestId) {
            $messageId = $header->message_id;
            $senderAddress = $header->senderaddress;

            $structure = imap_fetchstructure($mbox, $msgNo);
            $attachments = [];
            $hasAttachments = (property_exists($structure, "parts"));
            if ($hasAttachments) {
              foreach ($structure->parts as $part) {
                $disposition = (property_exists($part, "disposition") ? $part->disposition : null);
                if ($disposition === "attachment") {
                  $fileName = array_filter($part->dparameters, function($param) { return $param->attribute === "filename"; });
                  if (count($fileName) > 0) {
                    $attachments[] = $fileName[0]->value;
                  }
                }
              }
            }

            $body = imap_fetchbody($mbox, $msgNo, "1");
            $body = $this->parseBody($body);

            $messages[] = [
              "messageId" => $messageId,
              "requestId" => $requestId,
              "timestamp" => $date->getTimestamp(),
              "from" => $senderAddress,
              "body" => $body,
              "attachments" => $attachments
            ];
          }
        }
      }

      return true;
    }

    public function execute($values = array()): bool {
      if (!parent::execute($values)) {
        return false;
      }

      if (!function_exists("imap_open")) {
        return $this->createError("IMAP is not enabled. Enable it inside the php config. For more information visit: https://www.php.net/manual/en/imap.setup.php");
      }

      $messageIds = $this->fetchMessageIds();
      if ($messageIds === false) {
        return false;
      } else if (count($messageIds) === 0) {
        // nothing to sync here
        return true;
      }

      $mailConfig = $this->getMailConfig();
      if (!$this->success) {
        return false;
      }

      $mbox = $this->connect($mailConfig);
      if ($mbox === false) {
        return false;
      }

      $boxes = $this->listFolders($mailConfig, $mbox);
      if ($boxes === false) {
        return false;
      }

      $now = (new \DateTime())->getTimestamp();
      $lastSync = intval($mailConfig->getProperty("last_sync", "0"));
      if ($lastSync > 0) {
        $lastSyncDateTime = (new \DateTime())->setTimeStamp($lastSync);
        $dateStr = $lastSyncDateTime->format("d-M-Y");
        $searchCriteria = "SINCE \"$dateStr\"";
      } else {
        $lastSyncDateTime = null;
        $searchCriteria = "ALL";
      }

      $messages = [];
      foreach ($boxes as $box) {
        imap_reopen($mbox, $box);
        if (!$this->runSearch($mbox, $searchCriteria, $lastSyncDateTime, $messages)) {
          return false;
        }
      }

      @imap_close($mbox);
      if (!empty($messages) && !$this->insertMessages($messages)) {
        return false;
      }

      $req = new \Api\Settings\Set($this->user);
      $this->success = $req->execute(array("settings" => array("mail_last_sync" => "$now")));
      $this->lastError = $req->getLastError();
      return $this->success;
    }
  }
}