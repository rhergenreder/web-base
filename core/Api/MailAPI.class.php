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
        $connectionData->setProperty("mail_footer", $settings["mail_footer"] ?? "");
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
  use DateTimeInterface;
  use Driver\SQL\Column\Column;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondIn;
  use Driver\SQL\Strategy\UpdateStrategy;
  use External\PHPMailer\Exception;
  use External\PHPMailer\PHPMailer;
  use Objects\ConnectionData;
  use Objects\GpgKey;
  use Objects\User;

  class Test extends MailAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "receiver" => new Parameter("receiver", Parameter::TYPE_EMAIL),
        "gpgFingerprint" => new StringType("gpgFingerprint", 64, true, null)
      ));
    }

    public function _execute(): bool {

      $receiver = $this->getParam("receiver");
      $req = new \Api\Mail\Send($this->user);
      $this->success = $req->execute(array(
        "to" => $receiver,
        "subject" => "Test E-Mail",
        "body" => "Hey! If you receive this e-mail, your mail configuration seems to be working.",
        "gpgFingerprint" => $this->getParam("gpgFingerprint"),
        "asnyc" => false
      ));

      $this->lastError = $req->getLastError();
      return $this->success;
    }
  }

  // TODO: expired gpg keys?
  class Send extends MailAPI {
    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'to' => new Parameter('to', Parameter::TYPE_EMAIL, true, null),
        'subject' => new StringType('subject', -1),
        'body' => new StringType('body', -1),
        'replyTo' => new Parameter('replyTo', Parameter::TYPE_EMAIL, true, null),
        'replyName' => new StringType('replyName', 32, true, ""),
        "gpgFingerprint" => new StringType("gpgFingerprint", 64, true, null),
        'async' => new Parameter("async", Parameter::TYPE_BOOLEAN, true, true)
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $mailConfig = $this->getMailConfig();
      if (!$this->success || $mailConfig === null) {
        return false;
      }

      $fromMail = $mailConfig->getProperty('from');
      $mailFooter = $mailConfig->getProperty('mail_footer');
      $toMail = $this->getParam('to') ?? $fromMail;
      $subject = $this->getParam('subject');
      $replyTo = $this->getParam('replyTo');
      $replyName = $this->getParam('replyName');
      $body = $this->getParam('body');
      $gpgFingerprint = $this->getParam("gpgFingerprint");

      if ($this->getParam("async")) {
        $sql = $this->user->getSQL();
        $this->success = $sql->insert("MailQueue", ["from", "to", "subject", "body",
          "replyTo", "replyName", "gpgFingerprint"])
          ->addRow($fromMail, $toMail, $subject, $body, $replyTo, $replyName, $gpgFingerprint)
          ->execute() !== false;
        $this->lastError = $sql->getLastError();
        return $this->success;
      }


      if (stripos($body, "<body") === false) {
        $body = "<body>$body</body>";
      }
      if (stripos($body, "<html") === false) {
        $body = "<html>$body</html>";
      }

      if (!empty($mailFooter)) {
        $email_signature = realpath(WEBROOT . DIRECTORY_SEPARATOR . $mailFooter);
        if (is_file($email_signature)) {
          $email_signature = file_get_contents($email_signature);
          $body .= $email_signature;
        }
      }

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
        $mail->Timeout = 15;
        $mail->Username = $mailConfig->getLogin();
        $mail->Password = $mailConfig->getPassword();
        $mail->SMTPSecure = 'tls';
        $mail->CharSet = 'UTF-8';

        if ($gpgFingerprint) {
          $encryptedHeaders = implode("\r\n", [
            "Date: " . (new \DateTime())->format(DateTimeInterface::RFC2822),
            "Content-Type: text/html",
            "Content-Transfer-Encoding: quoted-printable"
          ]);

          $mimeBody = $encryptedHeaders . "\r\n\r\n" . quoted_printable_encode($body);
          $res = GpgKey::encrypt($mimeBody, $gpgFingerprint);
          if ($res["success"]) {
            $encryptedBody = $res["data"];
            $mail->AltBody = '';
            $mail->Body = '';
            $mail->AllowEmpty = true;
            $mail->ContentType = PHPMailer::CONTENT_TYPE_MULTIPART_ENCRYPTED;
            $mail->addStringAttachment("Version: 1", null, PHPMailer::ENCODING_BASE64, "application/pgp-encrypted", "");
            $mail->addStringAttachment($encryptedBody, "encrypted.asc", PHPMailer::ENCODING_7BIT, "application/octet-stream", "");
          } else {
            return $this->createError($res["error"]);
          }
        } else {
          $mail->msgHTML($body);
          $mail->AltBody = strip_tags($body);
        }

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
  // TODO: attachments
  class Sync extends MailAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array());
      $this->loginRequired = true;
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

      $query = $sql->insert("ContactMessage", ["request_id", "user_id", "message", "messageId", "created_at"])
        ->onDuplicateKeyStrategy(new UpdateStrategy(["messageId"], ["message" => new Column("message")]));

      $entityIds = [];
      foreach ($messages as $message) {
        $requestId = $message["requestId"];
        $query->addRow(
          $requestId,
          $sql->select("uid")->from("User")->where(new Compare("email", $message["from"]))->limit(1),
          $message["body"],
          $message["messageId"],
          (new \DateTime())->setTimeStamp($message["timestamp"]),
        );

        if (!in_array($requestId, $entityIds)) {
          $entityIds[] = $requestId;
        }
      }

      $this->success = $query->execute();
      $this->lastError = $sql->getLastError();

      // Update entity log
      if ($this->success && count($entityIds) > 0) {
        $sql->update("EntityLog")
          ->set("modified", $sql->now())
          ->where(new CondIn(new Column("entityId"), $entityIds))
          ->execute();
      }

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

    private function getSenderAddress($header): string {
      if (property_exists($header, "reply_to") && count($header->reply_to) > 0) {
        $mailBox = $header->reply_to[0]->mailbox;
        $host = $header->reply_to[0]->host;
      } else if (property_exists($header, "from") && count($header->from) > 0) {
        $mailBox = $header->from[0]->mailbox;
        $host = $header->from[0]->host;
      } else {
        return "unknown_addr";
      }

      return "$mailBox@$host";
    }

    private function runSearch($mbox, string $searchCriteria, ?\DateTime $lastSyncDateTime, array $messageIds, array &$messages) {

      $result = @imap_search($mbox, $searchCriteria);
      if ($result === false) {
        $err = imap_last_error(); // might return false, if not messages were found, so we can just abort without throwing an error
        return empty($err) ? true : $this->createError("Could not run search: $err");
      }

      foreach ($result as $msgNo) {
        $header = imap_headerinfo($mbox, $msgNo);
        $date = $this->parseDate($header->date);
        if ($date === false) {
          return false;
        }

        if ($lastSyncDateTime === null || \datetimeDiff($lastSyncDateTime, $date) > 0) {

          $references = property_exists($header, "references") ?
            explode(" ", $header->references) : [];

          $requestId = $this->findContactRequest($messageIds, $references);
          if ($requestId) {
            $messageId = $header->message_id;
            $senderAddress = $this->getSenderAddress($header);

            $structure = imap_fetchstructure($mbox, $msgNo);
            $attachments = [];
            $hasAttachments = (property_exists($structure, "parts"));
            if ($hasAttachments) {
              foreach ($structure->parts as $part) {
                $disposition = (property_exists($part, "disposition") ? $part->disposition : null);
                if ($disposition === "attachment") {
                  $fileName = array_filter($part->dparameters, function ($param) {
                    return $param->attribute === "filename";
                  });
                  if (count($fileName) > 0) {
                    $attachments[] = $fileName[0]->value;
                  }
                }
              }
            }

            $body = imap_fetchbody($mbox, $msgNo, "1");
            $body = $this->parseBody($body);

            if (!isset($messageId[$messageId])) {
              $messages[$messageId] = [
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
      }

      return true;
    }

    public function _execute(): bool {

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
      if (!$this->success || $mailConfig === null) {
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
        if (!$this->runSearch($mbox, $searchCriteria, $lastSyncDateTime, $messageIds, $messages)) {
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

  class SendQueue extends MailAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, [
        "debug" => new Parameter("debug", Parameter::TYPE_BOOLEAN, true, false)
      ]);
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $debug = $this->getParam("debug");
      $startTime = time();
      if ($debug) {
        echo "Start of processing mail queue at $startTime" . PHP_EOL;
      }

      $sql = $this->user->getSQL();
      $res = $sql->select("uid", "from", "to", "subject", "body",
        "replyTo", "replyName", "gpgFingerprint", "retryCount")
        ->from("MailQueue")
        ->where(new Compare("retryCount", 0, ">"))
        ->where(new Compare("status", "waiting"))
        ->where(new Compare("nextTry", $sql->now(), "<="))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success && is_array($res)) {
        if ($debug) {
          echo "Found " . count($res) . " mails to send" . PHP_EOL;
        }

        $successfulMails = [];
        foreach ($res as $row) {

          if (time() - $startTime >= 45) {
            $this->lastError = "Not able to process whole mail queue within 45 seconds, will continue on next time";
            break;
          }

          $to = $row["to"];
          $subject = $row["subject"];

          if ($debug) {
            echo "Sending subject=$subject to=$to" . PHP_EOL;
          }

          $mailId = intval($row["uid"]);
          $retryCount = intval($row["retryCount"]);
          $req = new Send($this->user);
          $args = [
            "to" => $to,
            "subject" => $subject,
            "body" => $row["body"],
            "replyTo" => $row["replyTo"],
            "replyName" => $row["replyName"],
            "gpgFingerprint" => $row["gpgFingerprint"],
            "async" => false
          ];
          $success = $req->execute($args);
          $error = $req->getLastError();

          if (!$success) {
            $delay = [0, 720, 360, 60, 30, 1];
            $minutes = $delay[max(0, min(count($delay) - 1, $retryCount))];
            $nextTry = (new \DateTime())->modify("+$minutes minute");
            $sql->update("MailQueue")
              ->set("retryCount", $retryCount - 1)
              ->set("status", "error")
              ->set("errorMessage", $error)
              ->set("nextTry", $nextTry)
              ->where(new Compare("uid", $mailId))
              ->execute();
          } else {
            $successfulMails[] = $mailId;
          }
        }

        $this->success = count($successfulMails) === count($res);
        if (!empty($successfulMails)) {
          $res = $sql->update("MailQueue")
            ->set("status", "success")
            ->where(new CondIn(new Column("uid"), $successfulMails))
            ->execute();
          $this->success = $res !== false;
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }
  }
}