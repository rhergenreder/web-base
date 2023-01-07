<?php

namespace Core\Objects\DatabaseEntity;

use Core\API\Mail\Send;
use Core\Driver\SQL\Expression\CurrentTimeStamp;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\EnumArr;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;

class MailQueueItem extends DatabaseEntity {

  protected static array $entityLogConfig = [
    "update" => true,
    "delete" => true,
    "insert" => true,
    "lifetime" => 30
  ];

  const STATUS_WAITING = "waiting";
  const STATUS_SUCCESS = "success";
  const STATUS_ERROR = "error";
  const STATUS_ITEMS = [self::STATUS_WAITING, self::STATUS_SUCCESS, self::STATUS_ERROR];

  #[MaxLength(64)]
  private string $from;

  #[MaxLength(64)]
  private string $to;

  private string $subject;
  private string $body;

  #[MaxLength(64)]
  private ?string $replyTo;

  #[MaxLength(64)]
  private ?string $replyName;

  #[MaxLength(64)]
  private ?string $gpgFingerprint;

  #[EnumArr(self::STATUS_ITEMS)]
  #[DefaultValue(self::STATUS_WAITING)]
  private string $status;

  #[DefaultValue(5)]
  private int $retryCount;

  #[DefaultValue(CurrentTimeStamp::class)]
  private \DateTime $nextTry;

  private ?string $errorMessage;

  public function __construct(string $fromMail, string $toMail, string $subject, string $body,
                              ?string $replyTo, ?string $replyName, ?string $gpgFingerprint) {
    parent::__construct();
    $this->from = $fromMail;
    $this->to = $toMail;
    $this->subject = $subject;
    $this->body = $body;
    $this->replyTo = $replyTo;
    $this->replyName = $replyName;
    $this->gpgFingerprint = $gpgFingerprint;
    $this->retryCount =  5;
    $this->nextTry = new \DateTime();
    $this->errorMessage = null;
    $this->status = self::STATUS_WAITING;
  }

  public function send(Context $context): bool {

    $args = [
      "to" => $this->to,
      "subject" => $this->subject,
      "body" => $this->body,
      "replyTo" => $this->replyTo,
      "replyName" => $this->replyName,
      "gpgFingerprint" => $this->gpgFingerprint,
      "async" => false
    ];

    $req = new Send($context);
    $success = $req->execute($args);
    $this->errorMessage = $req->getLastError();

    $delay = [0, 720, 360, 60, 30, 1];
    $minutes = $delay[max(0, min(count($delay) - 1, $this->retryCount))];
    if ($this->retryCount > 0) {
      $this->retryCount--;
      $this->nextTry = (new \DateTime())->modify("+$minutes minute");
    } else if (!$success) {
      $this->status = self::STATUS_ERROR;
    }

    if ($success) {
      $this->status = self::STATUS_SUCCESS;
    }

    $this->save($context->getSQL(), ["status", "retry_count", "next_try", "error_message"]);
    return $success;
  }
}