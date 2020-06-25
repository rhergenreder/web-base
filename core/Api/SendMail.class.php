<?php

namespace Api;
use Api\Parameter\Parameter;
use Api\Parameter\StringType;
use External\PHPMailer\Exception;
use External\PHPMailer\PHPMailer;
use Objects\ConnectionData;

class SendMail extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      'from' => new Parameter('from', Parameter::TYPE_EMAIL),
      'to' => new Parameter('to', Parameter::TYPE_EMAIL),
      'subject'  => new StringType('subject', -1),
      'body' => new StringType('body', -1),
      'fromName' => new StringType('fromName', -1, true, ''),
      'replyTo' => new Parameter('to', Parameter::TYPE_EMAIL, true, ''),
    ));
    $this->isPublic = false;
  }

  private function getMailConfig() : ?ConnectionData {
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
      return new ConnectionData($host, $port, $login, $password);
    }

    return null;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $mailConfig = $this->getMailConfig();
    if (!$this->success) {
      return false;
    }

    try {
      $mail = new PHPMailer;
      $mail->IsSMTP();
      $mail->setFrom($this->getParam('from'), $this->getParam('fromName'));
      $mail->addAddress($this->getParam('to'));
      $mail->Subject = $this->getParam('subject');
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

      $replyTo = $this->getParam('replyTo');
      if(!is_null($replyTo) && !empty($replyTo)) {
        $mail->AddReplyTo($replyTo, $this->getParam('fromName'));
      }

      $this->success = @$mail->Send();
      if (!$this->success) {
        $this->lastError = "Error sending Mail: $mail->ErrorInfo";
        error_log("sendMail() failed: $mail->ErrorInfo");
      }
     } catch (Exception $e) {
      $this->success = false;
      $this->lastError = "Error sending Mail: $e";
    }

    return $this->success;
  }
}