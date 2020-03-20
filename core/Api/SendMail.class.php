<?php

namespace Api;
use Api\Parameter\Parameter;
use Api\Parameter\StringType;

class SendMail extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array(
      'from' => new Parameter('from', Parameter::TYPE_EMAIL),
      'to' => new Parameter('to', Parameter::TYPE_EMAIL),
      'subject'  => new StringType('subject', -1),
      'body' => new StringType('body', -1),
      'fromName' => new StringType('fromName', -1, true, ''),
      'replyTo' => new Parameter('to', Parameter::TYPE_EMAIL, true, ''),
    ));
    $this->isPublic = false;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $mailConfig = $this->user->getConfiguration()->getMail();
    $mail = new \External\PHPMailer\PHPMailer;
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
      $this->lastError = 'Error sending Mail: ' . $mail->ErrorInfo;
      error_log("sendMail() failed: " . $mail->ErrorInfo);
    }

    return $this->success;
  }
};

?>
