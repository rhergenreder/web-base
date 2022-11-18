<?php

namespace Core\API {

  use Core\Objects\ConnectionData;
  use Core\Objects\Context;

  abstract class MailAPI extends Request {

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }

    protected function getMailConfig(): ?ConnectionData {
      $req = new \Core\API\Settings\Get($this->context);
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

namespace Core\API\Mail {

  use Core\API\MailAPI;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use DateTimeInterface;
  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondIn;
  use Core\External\PHPMailer\Exception;
  use Core\External\PHPMailer\PHPMailer;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\GpgKey;

  class Test extends MailAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "receiver" => new Parameter("receiver", Parameter::TYPE_EMAIL),
        "gpgFingerprint" => new StringType("gpgFingerprint", 64, true, null)
      ));
    }

    public function _execute(): bool {

      $receiver = $this->getParam("receiver");
      $req = new \Core\API\Mail\Send($this->context);
      $this->success = $req->execute(array(
        "to" => $receiver,
        "subject" => "Test E-Mail",
        "body" => "Hey! If you receive this e-mail, your mail configuration seems to be working.",
        "gpgFingerprint" => $this->getParam("gpgFingerprint"),
        "async" => false
      ));

      $this->lastError = $req->getLastError();
      return $this->success;
    }
  }

  class Send extends MailAPI {
    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'to' => new Parameter('to', Parameter::TYPE_EMAIL, true, null),
        'subject' => new StringType('subject', -1),
        'body' => new StringType('body', -1),
        'replyTo' => new Parameter('replyTo', Parameter::TYPE_EMAIL, true, null),
        'replyName' => new StringType('replyName', 32, true, ""),
        'gpgFingerprint' => new StringType("gpgFingerprint", 64, true, null),
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
        $sql = $this->context->getSQL();
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
          $this->logger->error("sendMail() failed: $mail->ErrorInfo");
        } else {
          $this->result["messageId"] = $mail->getLastMessageID();
        }
      } catch (Exception $e) {
        $this->success = false;
        $this->lastError = "Error sending Mail: $e";
        $this->logger->error($this->lastError);
      }

      return $this->success;
    }
  }

  class SendQueue extends MailAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
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

      $sql = $this->context->getSQL();
      $res = $sql->select("id", "from", "to", "subject", "body",
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

          $mailId = intval($row["id"]);
          $retryCount = intval($row["retryCount"]);
          $req = new Send($this->context);
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
              ->where(new Compare("id", $mailId))
              ->execute();
          } else {
            $successfulMails[] = $mailId;
          }
        }

        $this->success = count($successfulMails) === count($res);
        if (!empty($successfulMails)) {
          $res = $sql->update("MailQueue")
            ->set("status", "success")
            ->where(new CondIn(new Column("id"), $successfulMails))
            ->execute();
          $this->success = $res !== false;
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }
  }
}