<?php

namespace Core\Objects\DatabaseEntity;

use Core\API\Parameter\Parameter;
use Core\Driver\SQL\Expression\CurrentTimeStamp;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\Enum;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;

class Notification extends DatabaseEntity {

  #[Enum('default', 'message', 'warning')] private string $type;
  #[DefaultValue(CurrentTimeStamp::class)] private \DateTime $createdAt;
  #[MaxLength(32)] public string $title;
  #[MaxLength(256)] public string $message;

  public function __construct(?int $id = null) {
    parent::__construct($id);
  }

  public function jsonSerialize(): array {
    return [
      "id" => $this->getId(),
      "createdAt" => $this->createdAt->format(Parameter::DATE_TIME_FORMAT),
      "title" => $this->title,
      "message" => $this->message
    ];
  }
}